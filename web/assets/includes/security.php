<?php
// +------------------------------------------------------------------------+
// | Jackisa internal security layer.                                        |
// |                                                                         |
// | Loaded once, globally, from assets/includes/app_start.php right after   |
// | the database connection is ready. It adds defense-in-depth (headers,    |
// | rate limiting, input hygiene, audit logging and session anomaly         |
// | detection) without changing any existing behaviour. Every guard fails   |
// | open on error and nothing here destroys a session or blocks normal use. |
// +------------------------------------------------------------------------+

if (!defined('PT_SECURITY_LAYER')) {
    define('PT_SECURITY_LAYER', 1);

    // ---------------------------------------------------------------------
    // Response hardening. Deliberately conservative: no X-Frame-Options or
    // Content-Security-Policy is emitted globally because the public embed
    // pages are meant to be framed by third-party sites and the templates
    // rely on inline scripts/styles. Those two headers are applied only to
    // the admin area by PT_SecurityAdminHeaders().
    // ---------------------------------------------------------------------
    if (!headers_sent()) {
        $pt_secure_request = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        if ($pt_secure_request) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        unset($pt_secure_request);
    }

    // ---------------------------------------------------------------------
    // Audit log. Mirrors the existing jpesa_reconciliation.log pattern.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityLog')) {
        function PT_SecurityLog($event, array $context = array())
        {
            $directory = __DIR__ . '/../logs';
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            $line = date('c') . ' ' . $event;
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $line .= ' ' . $key . '=' . str_replace(array("\r", "\n"), ' ', (string) $value);
                }
            }
            @file_put_contents($directory . '/security.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    // ---------------------------------------------------------------------
    // Client IP. Only X-Forwarded-For is trusted when the direct peer is a
    // configured proxy, otherwise a spoofed header could bypass rate limits.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityClientIp')) {
        function PT_SecurityClientIp()
        {
            $remote = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
            $trusted = defined('PT_SECURITY_TRUSTED_PROXIES') ? (array) PT_SECURITY_TRUSTED_PROXIES : array();
            if (!empty($trusted) && in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $candidate = trim($forwarded[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
            return $remote;
        }
    }

    // ---------------------------------------------------------------------
    // Fixed-window rate limiter backed by a dedicated table. Fails open so a
    // missing table or transient database error never locks anyone out.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityRateLimit')) {
        function PT_SecurityRateLimit($key, $max = 60, $window = 60)
        {
            global $db;
            if (empty($key) || empty($db)) {
                return true;
            }
            static $table_ready = false;
            try {
                if (!$table_ready) {
                    $db->rawQuery("CREATE TABLE IF NOT EXISTS `security_rate_limits` (`bucket` varchar(64) NOT NULL, `hits` int(11) NOT NULL DEFAULT 0, `window_start` int(11) NOT NULL DEFAULT 0, PRIMARY KEY (`bucket`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
                    $table_ready = true;
                }
                $bucket = substr(sha1((string) $key), 0, 40);
                $now = time();
                $row = $db->where('bucket', $bucket)->getOne('security_rate_limits');
                if (!empty($row) && $now - (int) $row->window_start < (int) $window) {
                    $hits = (int) $row->hits + 1;
                    $db->where('bucket', $bucket)->update('security_rate_limits', array('hits' => $hits));
                } else {
                    $hits = 1;
                    if (!empty($row)) {
                        $db->where('bucket', $bucket)->update('security_rate_limits', array('hits' => 1, 'window_start' => $now));
                    } else {
                        $db->insert('security_rate_limits', array('bucket' => $bucket, 'hits' => 1, 'window_start' => $now));
                    }
                }
                return $hits <= (int) $max;
            } catch (Throwable $exception) {
                return true;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Recursively strip null bytes and control characters from request input.
    // Printable content, tabs and newlines are preserved so nothing the user
    // legitimately typed is altered.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityScrubValue')) {
        function PT_SecurityScrubValue($value)
        {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = PT_SecurityScrubValue($item);
                }
                return $value;
            }
            if (is_string($value)) {
                // The /u pattern returns null on invalid UTF-8; fall back to a byte-safe
                // pattern so a malformed request cannot blank out a field.
                $scrubbed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
                if ($scrubbed === null) {
                    $scrubbed = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                }
                return $scrubbed;
            }
            return $value;
        }
    }

    if (!function_exists('PT_SecurityScrubGlobals')) {
        function PT_SecurityScrubGlobals()
        {
            foreach (array('_GET', '_POST', '_COOKIE', '_REQUEST') as $global_name) {
                if (!empty($GLOBALS[$global_name]) && is_array($GLOBALS[$global_name])) {
                    $GLOBALS[$global_name] = PT_SecurityScrubValue($GLOBALS[$global_name]);
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Session anomaly detection. Records a client fingerprint and logs a
    // change (possible hijack / fixation attempt) but never invalidates the
    // session, so mobile users whose network changes stay logged in.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityTouchSession')) {
        function PT_SecurityTouchSession()
        {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return;
            }
            $fingerprint = substr(sha1(
                (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . '|' .
                (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
            ), 0, 32);
            if (empty($_SESSION['pt_sec_fp'])) {
                $_SESSION['pt_sec_fp'] = $fingerprint;
                $_SESSION['pt_sec_started'] = time();
                return;
            }
            if (!hash_equals((string) $_SESSION['pt_sec_fp'], $fingerprint)) {
                PT_SecurityLog('session_fingerprint_changed', array(
                    'ip' => PT_SecurityClientIp(),
                    'user' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '-'
                ));
                // Adopt the new fingerprint so a single change is only reported once.
                $_SESSION['pt_sec_fp'] = $fingerprint;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Route aware guards for the most abuse-prone entry points. Limits are
    // intentionally generous so normal users are never affected.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityApplyRouteGuards')) {
        function PT_SecurityApplyRouteGuards()
        {
            $link = isset($_GET['link1']) ? (string) $_GET['link1'] : '';
            $ip = PT_SecurityClientIp();
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
            if ($method === 'POST' && $link === 'login') {
                if (!PT_SecurityRateLimit('login:' . $ip, 40, 900)) {
                    PT_SecurityLog('login_rate_limited', array('ip' => $ip));
                    http_response_code(429);
                    exit('Too many login attempts. Please wait a few minutes and try again.');
                }
            }
            if ($method === 'POST' && $link === 'register') {
                if (!PT_SecurityRateLimit('register:' . $ip, 15, 3600)) {
                    PT_SecurityLog('register_rate_limited', array('ip' => $ip));
                    http_response_code(429);
                    exit('Too many registration attempts. Please wait and try again.');
                }
            }
            if ($link === 'two_factor_submit' && $method === 'POST') {
                if (!PT_SecurityRateLimit('twofactor:' . $ip, 30, 900)) {
                    PT_SecurityLog('two_factor_rate_limited', array('ip' => $ip));
                    http_response_code(429);
                    exit('Too many attempts. Please wait and try again.');
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Admin area hardening: apply the strict frame policy that public pages
    // cannot use, and rate limit admin requests.
    // ---------------------------------------------------------------------
    if (!function_exists('PT_SecurityAdminHeaders')) {
        function PT_SecurityAdminHeaders()
        {
            if (!headers_sent()) {
                header('X-Frame-Options: SAMEORIGIN');
                header('Content-Security-Policy: frame-ancestors \'self\'');
            }
        }
    }

    if (!function_exists('PT_SecurityAdminGate')) {
        function PT_SecurityAdminGate()
        {
            PT_SecurityAdminHeaders();
            $ip = PT_SecurityClientIp();
            if (!PT_SecurityRateLimit('admincp:' . $ip, 300, 300)) {
                PT_SecurityLog('admin_rate_limited', array('ip' => $ip));
                http_response_code(429);
                exit('Too many requests. Please slow down.');
            }
        }
    }

    // ---------------------------------------------------------------------
    // Apply the global pieces now.
    // ---------------------------------------------------------------------
    PT_SecurityScrubGlobals();
    PT_SecurityTouchSession();
    PT_SecurityApplyRouteGuards();
}
