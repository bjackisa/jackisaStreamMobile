<?php
/**
 * Autocontent articles cron.
 *
 * Run from the command line, for example every hour:
 *   php /path/to/autocontent_articles_cron.php
 *
 * On every run it reads each configured RSS/Atom feed, keeps the items that are not yet
 * posted in the system (matched by title per author), and inserts the new ones as articles
 * in pt_posts for the mapped user. Each article keeps the feed's own image link when the
 * feed exposes one (media:thumbnail, media:content, enclosure, itunes:image or the first
 * <img> in the body).
 *
 * Safe to run repeatedly: every candidate is checked against pt_posts before insertion, so
 * a second run only skips duplicates and never double-posts. No lock file is used, matching
 * the existing yt_cron.php design.
 */

require_once __DIR__ . '/assets/init.php';
decryptConfigData();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cron may only be run from the command line.\n");
}

ini_set('output_buffering', 'off');
ini_set('implicit_flush', '1');
if (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

define('AUTOCONTENT_ARTICLES_MAX_PER_FEED', 8);
define('AUTOCONTENT_ARTICLES_USER_AGENT', 'Mozilla/5.0 (compatible; JackisaBot/1.0; +https://jackisa.com)');

/**
 * Feed map: user_id => list of feeds. Each feed is a URL, optionally with a 'category'
 * (a language key such as '5' for Sports) and a human readable 'name' used for attribution.
 * The category is validated against the live category list at runtime and falls back to
 * 'other' when it does not exist, so a stale value can never hide an article.
 */
$autocontent_articles_feeds = array(
    17 => array(
        array('url' => 'https://news.ycombinator.com/rss', 'name' => 'Hacker News', 'category' => '11'),
        array('url' => 'https://www.reddit.com/r/technology/top.rss?t=day', 'name' => 'Reddit Technology', 'category' => '11'),
        array('url' => 'http://rss.slashdot.org/Slashdot/slashdotMain', 'name' => 'Slashdot', 'category' => '11'),
        array('url' => 'https://www.theverge.com/rss/tech/index.xml', 'name' => 'The Verge', 'category' => '11'),
        array('url' => 'https://techcrunch.com/feed/', 'name' => 'TechCrunch', 'category' => '11'),
        array('url' => 'https://www.theguardian.com/us/technology/rss', 'name' => 'The Guardian Technology', 'category' => '11'),
        array('url' => 'https://feeds.arstechnica.com/arstechnica/technology-lab', 'name' => 'Ars Technica', 'category' => '11'),
        array('url' => 'https://www.reutersagency.com/feed/?best-topics=tech&post_type=best', 'name' => 'Reuters Tech', 'category' => '11'),
        array('url' => 'https://feeds.a.dj.com/rss/RSSWSJD.xml', 'name' => 'WSJ Tech', 'category' => '11'),
        array('url' => 'https://www.ft.com/technology?format=rss', 'name' => 'Financial Times Technology', 'category' => '11'),
        array('url' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss', 'name' => 'NASA', 'category' => '11'),
        array('url' => 'https://9to5mac.com/feed/', 'name' => '9to5Mac', 'category' => '11'),
        array('url' => 'https://www.androidauthority.com/feed/', 'name' => 'Android Authority', 'category' => '11'),
        array('url' => 'https://feeds.arstechnica.com/arstechnica/index', 'name' => 'Ars Technica', 'category' => '11'),
        array('url' => 'https://www.bleepingcomputer.com/feed/', 'name' => 'BleepingComputer', 'category' => '11'),
        array('url' => 'https://www.cnet.com/rss/news/', 'name' => 'CNET', 'category' => '11'),
        array('url' => 'https://www.digitaltrends.com/feed/', 'name' => 'Digital Trends', 'category' => '11'),
        array('url' => 'https://www.engadget.com/rss.xml', 'name' => 'Engadget', 'category' => '11'),
        array('url' => 'https://feeds.macrumors.com/MacRumors-All', 'name' => 'MacRumors', 'category' => '11'),
        array('url' => 'https://www.technologyreview.com/feed/', 'name' => 'MIT Technology Review', 'category' => '11'),
    ),
    2 => array(
        array('url' => 'http://feeds.bbci.co.uk/sport/rss.xml', 'name' => 'BBC Sport', 'category' => '5'),
        array('url' => 'http://feeds.bbci.co.uk/sport/football/rss.xml', 'name' => 'BBC Football', 'category' => '5'),
        array('url' => 'https://www.skysports.com/rss/0,20514,11095,00.xml', 'name' => 'Sky Sports', 'category' => '5'),
        array('url' => 'https://theathletic.com/rss-feed/', 'name' => 'The Athletic', 'category' => '5'),
        array('url' => 'https://www.formula1.com/content/fom-website/en/latest/all.xml', 'name' => 'Formula 1', 'category' => '5'),
        array('url' => 'https://sports.yahoo.com/rss/', 'name' => 'Yahoo Sports', 'category' => '5'),
        array('url' => 'https://www.cbssports.com/rss/headlines/', 'name' => 'CBS Sports', 'category' => '5'),
        array('url' => 'https://www.espn.com/espn/rss/news', 'name' => 'ESPN', 'category' => '5'),
        array('url' => 'https://www.sbnation.com/rss/index.xml', 'name' => 'SB Nation', 'category' => '5'),
        array('url' => 'https://www.skysports.com/rss/12040', 'name' => 'Sky Sports', 'category' => '5'),
    ),
    3 => array(
        array('url' => 'https://www.christianitytoday.com/feed/', 'name' => 'Christianity Today', 'category' => 'other'),
        array('url' => 'https://www.christiantoday.com/rss/feed', 'name' => 'Christian Today', 'category' => 'other'),
        array('url' => 'https://www.christianpost.com/rss', 'name' => 'The Christian Post', 'category' => 'other'),
        array('url' => 'https://www.christiantoday.com/rss.xml', 'name' => 'Christian Today', 'category' => 'other'),
        array('url' => 'http://rss.csmonitor.com/feeds/usa', 'name' => 'Christian Science Monitor', 'category' => 'other'),
        array('url' => 'https://www.christianpost.com/rss/feed/', 'name' => 'The Christian Post', 'category' => 'other'),
    ),
    4 => array(
        array('url' => 'https://abcnews.go.com/abcnews/internationalheadlines', 'name' => 'ABC News International', 'category' => '11'),
        array('url' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'name' => 'BBC World', 'category' => '11'),
        array('url' => 'http://rss.cnn.com/rss/edition.rss', 'name' => 'CNN', 'category' => '11'),
        array('url' => 'https://feeds.nbcnews.com/nbcnews/public/news', 'name' => 'NBC News', 'category' => '11'),
        array('url' => 'https://feeds.npr.org/1001/rss.xml', 'name' => 'NPR', 'category' => '11'),
        array('url' => 'https://www.theguardian.com/world/rss', 'name' => 'The Guardian World', 'category' => '11'),
        array('url' => 'https://www.independent.co.uk/news/world/rss', 'name' => 'The Independent World', 'category' => '11'),
        array('url' => 'https://feeds.bbci.co.uk/news/rss.xml', 'name' => 'BBC News', 'category' => '11'),
        array('url' => 'http://feeds.skynews.com/feeds/rss/world.xml', 'name' => 'Sky News World', 'category' => '11'),
        array('url' => 'https://www.cbc.ca/webfeed/rss/rss-topstories?_=1779115307971', 'name' => 'CBC Top Stories', 'category' => '11'),
    ),
    24 => array(
        array('url' => 'https://www.bonappetit.com/feed/rss', 'name' => 'Bon Appetit', 'category' => 'other'),
        array('url' => 'https://www.cntraveler.com/feed/rss', 'name' => 'Condé Nast Traveler', 'category' => '6'),
        array('url' => 'https://www.eater.com/rss/index.xml', 'name' => 'Eater', 'category' => 'other'),
        array('url' => 'https://www.playstationlifestyle.net/feed/', 'name' => 'PlayStation Lifestyle', 'category' => '7'),
        array('url' => 'https://feeds.content.dowjones.io/public/rss/RSSLifestyle', 'name' => 'Dow Jones Lifestyle', 'category' => 'other'),
        array('url' => 'https://metro.co.uk/lifestyle/feed/', 'name' => 'Metro Lifestyle', 'category' => 'other'),
    ),
    23 => array(
        array('url' => 'https://www.christianitytoday.com/feed/', 'name' => 'Christianity Today', 'category' => 'other'),
        array('url' => 'https://www.christianpost.com/rss', 'name' => 'The Christian Post', 'category' => 'other'),
        array('url' => 'https://www.christiantoday.com/rss/feed', 'name' => 'Christian Today', 'category' => 'other'),
        array('url' => 'https://www.christiancentury.org/rss.xml', 'name' => 'The Christian Century', 'category' => 'other'),
        array('url' => 'https://www.relevantmagazine.com/feed', 'name' => 'RELEVANT', 'category' => 'other'),
        array('url' => 'https://www.crosswalk.com/rss/', 'name' => 'Crosswalk', 'category' => 'other'),
        array('url' => 'https://www.biblegateway.com/blog/feed/', 'name' => 'Bible Gateway', 'category' => 'other'),
        array('url' => 'https://ifapray.org/feed', 'name' => 'Intercessors for America', 'category' => 'other'),
        array('url' => 'https://www.worthynews.com/feed', 'name' => 'Worthy News', 'category' => 'other'),
        array('url' => 'https://www.christianheadlines.com/feed/', 'name' => 'Christian Headlines', 'category' => 'other'),
        array('url' => 'https://www.christiandaily.com/rss.xml', 'name' => 'Christian Daily', 'category' => 'other'),
        array('url' => 'https://www.thegospelcoalition.org/feed/', 'name' => 'The Gospel Coalition', 'category' => 'other'),
        array('url' => 'https://www.lifesitenews.com/feed/', 'name' => 'LifeSiteNews', 'category' => 'other'),
    ),
    22 => array(
        array('url' => 'https://www.mindful.org/feed/', 'name' => 'Mindful', 'category' => 'other'),
        array('url' => 'https://tricycle.org/feed/', 'name' => 'Tricycle', 'category' => 'other'),
        array('url' => 'https://www.lionsroar.com/feed/', 'name' => 'Lion\'s Roar', 'category' => 'other'),
        array('url' => 'https://www.himalayanacademy.com/feed', 'name' => 'Himalayan Academy', 'category' => 'other'),
        array('url' => 'https://www.yogajournal.com/feed/', 'name' => 'Yoga Journal', 'category' => 'other'),
        array('url' => 'https://tinybuddha.com/feed/', 'name' => 'Tiny Buddha', 'category' => 'other'),
        array('url' => 'https://www.scienceofmind.com/feed', 'name' => 'Science of Mind', 'category' => 'other'),
        array('url' => 'https://www.dailygood.org/feed', 'name' => 'DailyGood', 'category' => 'other'),
        array('url' => 'https://www.huffpost.com/section/religion/feed', 'name' => 'HuffPost Religion', 'category' => 'other'),
    ),
    21 => array(
        array('url' => 'https://theworldsikhnews.com/feed', 'name' => 'The World Sikh News', 'category' => 'other'),
        array('url' => 'https://feeds.feedburner.com/GurbaniBlog', 'name' => 'Gurbani Blog', 'category' => 'other'),
        array('url' => 'https://kaurlife.org/feed', 'name' => 'Kaur Life', 'category' => 'other'),
        array('url' => 'https://sikhsangat.org/feed', 'name' => 'Sikh Sangat News', 'category' => 'other'),
        array('url' => 'https://sikhsoldier.org/feed', 'name' => 'Sikh Soldier Organization', 'category' => 'other'),
        array('url' => 'https://sikhfoundation.org/feed', 'name' => 'Sikh Foundation', 'category' => 'other'),
        array('url' => 'https://sikhsiyasat.net/feed/', 'name' => 'Sikh Siyasat', 'category' => 'other'),
        array('url' => 'https://www.amarujala.com/rss/punjab.xml', 'name' => 'Amar Ujala Punjab', 'category' => 'other'),
    ),
    20 => array(
        array('url' => 'https://www.hinduwebsite.com/rss/topstories.xml', 'name' => 'Hinduwebsite', 'category' => 'other'),
        array('url' => 'https://www.hinduismtoday.com/feed/', 'name' => 'Hinduism Today', 'category' => 'other'),
        array('url' => 'https://www.himalayanacademy.com/feed', 'name' => 'Himalayan Academy', 'category' => 'other'),
        array('url' => 'https://www.indiadivine.org/feed/', 'name' => 'IndiaDivine', 'category' => 'other'),
        array('url' => 'https://www.thehindu.com/feeder/default.rss', 'name' => 'The Hindu', 'category' => 'other'),
        array('url' => 'https://www.hindu-blog.com/feeds/posts/default', 'name' => 'Hindu Blog', 'category' => 'other'),
        array('url' => 'https://www.vedicfeed.com/feed/', 'name' => 'VedicFeed', 'category' => 'other'),
    ),
    19 => array(
        array('url' => 'https://www.jns.org/index.rss', 'name' => 'Jewish News Syndicate', 'category' => 'other'),
        array('url' => 'https://www.jpost.com/rss/rssfeedsfrontpage.aspx', 'name' => 'The Jerusalem Post', 'category' => 'other'),
        array('url' => 'https://aish.com/feed/', 'name' => 'Aish.com', 'category' => 'other'),
        array('url' => 'https://www.myjewishlearning.com/feed', 'name' => 'My Jewish Learning', 'category' => 'other'),
        array('url' => 'https://forward.com/feed/', 'name' => 'The Forward', 'category' => 'other'),
        array('url' => 'https://www.jewishpress.com/feed/', 'name' => 'The Jewish Press', 'category' => 'other'),
        array('url' => 'https://www.algemeiner.com/feed/', 'name' => 'The Algemeiner', 'category' => 'other'),
        array('url' => 'https://www.jta.org/feed', 'name' => 'Jewish Telegraphic Agency', 'category' => 'other'),
        array('url' => 'https://www.theyeshivaworld.com/feed/', 'name' => 'The Yeshiva World', 'category' => 'other'),
    ),
    18 => array(
        array('url' => 'https://aboutislam.net/feed/', 'name' => 'About Islam', 'category' => 'other'),
        array('url' => 'https://www.muslimmatters.org/feed/', 'name' => 'MuslimMatters', 'category' => 'other'),
        array('url' => 'https://islamonline.net/feed/', 'name' => 'IslamOnline', 'category' => 'other'),
        array('url' => 'https://www.soundvision.com/rss.xml', 'name' => 'SoundVision', 'category' => 'other'),
        array('url' => 'https://www.aljazeera.com/xml/rss/all.xml', 'name' => 'Al Jazeera', 'category' => 'other'),
        array('url' => 'https://islamonline.net/en/feed/', 'name' => 'IslamOnline English', 'category' => 'other'),
        array('url' => 'https://themuslimvibe.com/feed', 'name' => 'The Muslim Vibe', 'category' => 'other'),
        array('url' => 'https://www.middleeasteye.net/rss', 'name' => 'Middle East Eye', 'category' => 'other'),
    ),
    16 => array(
        array('url' => 'https://www.entrepreneur.com/latest.rss', 'name' => 'Entrepreneur', 'category' => 'other'),
        array('url' => 'https://www.inc.com/rss/', 'name' => 'Inc.', 'category' => 'other'),
        array('url' => 'https://www.businessinsider.com/rss', 'name' => 'Business Insider', 'category' => 'other'),
        array('url' => 'https://sifted.eu/feed', 'name' => 'Sifted', 'category' => 'other'),
        array('url' => 'https://news.crunchbase.com/feed/', 'name' => 'Crunchbase News', 'category' => 'other'),
        array('url' => 'https://www.startupnation.com/feed/', 'name' => 'StartupNation', 'category' => 'other'),
        array('url' => 'https://techcabal.com/feed/', 'name' => 'TechCabal', 'category' => 'other'),
        array('url' => 'https://www.smallbiztrends.com/feed/', 'name' => 'Small Business Trends', 'category' => 'other'),
        array('url' => 'https://www.fastcompany.com/rss', 'name' => 'Fast Company', 'category' => 'other'),
    ),
    15 => array(
        array('url' => 'https://www.theguardian.com/sport/rss', 'name' => 'The Guardian Sport', 'category' => '5'),
        array('url' => 'https://www.telegraph.co.uk/sport/rss.xml', 'name' => 'The Telegraph Sport', 'category' => '5'),
        array('url' => 'https://www.sportingnews.com/us/rss', 'name' => 'Sporting News', 'category' => '5'),
        array('url' => 'https://www.90min.com/posts.rss', 'name' => '90min', 'category' => '5'),
        array('url' => 'https://www.skysports.com/rss/11095', 'name' => 'Sky Sports', 'category' => '5'),
        array('url' => 'https://www.cbssports.com/rss/headlines/', 'name' => 'CBS Sports', 'category' => '5'),
        array('url' => 'https://www.givemesport.com/feed', 'name' => 'GiveMeSport', 'category' => '5'),
    ),
    14 => array(
        array('url' => 'https://pitchfork.com/feed/feed-news/rss', 'name' => 'Pitchfork', 'category' => '3'),
        array('url' => 'https://pitchfork.com/feed/reviews/best/tracks/rss', 'name' => 'Pitchfork Best New Tracks', 'category' => '3'),
        array('url' => 'https://www.billboard.com/feed/', 'name' => 'Billboard', 'category' => '3'),
        array('url' => 'https://www.nme.com/news/music/feed', 'name' => 'NME', 'category' => '3'),
        array('url' => 'https://www.rollingstone.com/music/feed/', 'name' => 'Rolling Stone', 'category' => '3'),
        array('url' => 'https://consequence.net/feed/', 'name' => 'Consequence', 'category' => '3'),
        array('url' => 'https://www.stereogum.com/feed/', 'name' => 'Stereogum', 'category' => '3'),
        array('url' => 'https://www.spin.com/feed/', 'name' => 'SPIN', 'category' => '3'),
        array('url' => 'https://djmag.com/rss.xml', 'name' => 'DJ Mag', 'category' => '3'),
        array('url' => 'https://www.musicbusinessworldwide.com/feed/', 'name' => 'Music Business Worldwide', 'category' => '3'),
    ),
    13 => array(
        array('url' => 'https://deadline.com/v/tv/feed', 'name' => 'Deadline TV', 'category' => '10'),
        array('url' => 'https://www.tvline.com/feed/', 'name' => 'TVLine', 'category' => '10'),
        array('url' => 'https://www.hollywoodreporter.com/c/tv/feed/', 'name' => 'The Hollywood Reporter TV', 'category' => '10'),
        array('url' => 'https://variety.com/v/tv/feed/', 'name' => 'Variety TV', 'category' => '10'),
        array('url' => 'https://www.tvinsider.com/feed/', 'name' => 'TV Insider', 'category' => '10'),
        array('url' => 'https://decider.com/feed/', 'name' => 'Decider', 'category' => '10'),
        array('url' => 'https://www.tvfanatic.com/rss.xml', 'name' => 'TV Fanatic', 'category' => '10'),
        array('url' => 'https://www.spoilertv.com/feeds/posts/default', 'name' => 'SpoilerTV', 'category' => '10'),
        array('url' => 'https://www.whats-on-netflix.com/feed/', 'name' => "What's on Netflix", 'category' => '10'),
    ),
    12 => array(
        array('url' => 'https://www.documentary.org/rss.xml', 'name' => 'International Documentary Association', 'category' => '1'),
        array('url' => 'https://nonfictionfilm.com/news/feed', 'name' => 'Non Fiction Film', 'category' => '1'),
        array('url' => 'https://blog.nfb.ca/blog/category/documentary/feed/', 'name' => 'NFB Blog Documentary', 'category' => '1'),
        array('url' => 'https://www.documentarydrive.com/feed/', 'name' => 'Documentary Drive', 'category' => '1'),
        array('url' => 'https://www.pbs.org/newshour/feeds/rss/headlines', 'name' => 'PBS NewsHour', 'category' => '1'),
    ),
    11 => array(
        array('url' => 'https://www.indiewire.com/feed/', 'name' => 'IndieWire', 'category' => '1'),
        array('url' => 'https://www.slashfilm.com/feed/', 'name' => '/Film', 'category' => '1'),
        array('url' => 'https://collider.com/feed/', 'name' => 'Collider', 'category' => '1'),
        array('url' => 'https://screenrant.com/feed/', 'name' => 'Screen Rant', 'category' => '1'),
        array('url' => 'https://www.firstshowing.net/feed/', 'name' => 'FirstShowing.net', 'category' => '1'),
        array('url' => 'https://www.movieweb.com/feed/', 'name' => 'MovieWeb', 'category' => '1'),
        array('url' => 'https://www.hollywoodreporter.com/c/movies/feed/', 'name' => 'The Hollywood Reporter Movies', 'category' => '1'),
        array('url' => 'https://variety.com/v/film/feed/', 'name' => 'Variety Film', 'category' => '1'),
        array('url' => 'https://www.cinemablend.com/rss', 'name' => 'CinemaBlend', 'category' => '1'),
        array('url' => 'https://www.joblo.com/feed/', 'name' => 'JoBlo', 'category' => '1'),
    ),
    8 => array(
        array('url' => 'https://dogtime.com/feed', 'name' => 'DogTime', 'category' => '4'),
        array('url' => 'https://www.dogster.com/feed', 'name' => 'Dogster', 'category' => '4'),
        array('url' => 'https://iheartdogs.com/feed/', 'name' => 'iHeartDogs', 'category' => '4'),
        array('url' => 'https://www.dogingtonpost.com/feed/', 'name' => 'The Dogington Post', 'category' => '4'),
        array('url' => 'https://www.catster.com/feed', 'name' => 'Catster', 'category' => '4'),
        array('url' => 'https://www.hepper.com/feed/', 'name' => 'Hepper', 'category' => '4'),
        array('url' => 'https://www.whole-dog-journal.com/feed/', 'name' => 'Whole Dog Journal', 'category' => '4'),
        array('url' => 'https://www.akc.org/expert-advice/feed/', 'name' => 'American Kennel Club', 'category' => '4'),
        array('url' => 'https://www.vetstreet.com/feed', 'name' => 'Vetstreet', 'category' => '4'),
    ),
    7 => array(
        array('url' => 'https://www.computerworld.com/feed/', 'name' => 'Computerworld', 'category' => '12'),
        array('url' => 'https://www.techrepublic.com/rssfeeds/articles/', 'name' => 'TechRepublic', 'category' => '12'),
        array('url' => 'https://ictevangelist.com/feed', 'name' => 'ICTEvangelist', 'category' => '12'),
        array('url' => 'https://www.ictworks.org/feed/', 'name' => 'ICTworks', 'category' => '12'),
        array('url' => 'https://www.techmeme.com/feed.xml', 'name' => 'Techmeme', 'category' => '12'),
        array('url' => 'https://www.cio.com/feed/', 'name' => 'CIO', 'category' => '12'),
        array('url' => 'https://www.infoworld.com/feed/', 'name' => 'InfoWorld', 'category' => '12'),
        array('url' => 'https://www.codingdojo.com/blog/feed', 'name' => 'Coding Dojo', 'category' => '12'),
    ),
    6 => array(
        array('url' => 'https://www.movieweb.com/feed/', 'name' => 'MovieWeb', 'category' => '1'),
        array('url' => 'https://screenrant.com/feed/', 'name' => 'Screen Rant', 'category' => '1'),
        array('url' => 'https://www.slashfilm.com/feed/', 'name' => '/Film', 'category' => '1'),
        array('url' => 'https://collider.com/feed/', 'name' => 'Collider', 'category' => '1'),
        array('url' => 'https://www.cinemablend.com/rss', 'name' => 'CinemaBlend', 'category' => '1'),
        array('url' => 'https://www.joblo.com/feed/', 'name' => 'JoBlo', 'category' => '1'),
        array('url' => 'https://www.screencrush.com/feed/', 'name' => 'ScreenCrush', 'category' => '1'),
        array('url' => 'https://www.comingsoon.net/feed', 'name' => 'ComingSoon.net', 'category' => '1'),
    ),
    5 => array(
        array('url' => 'https://pagesix.com/feed/', 'name' => 'Page Six', 'category' => '10'),
        array('url' => 'https://www.popsugar.com/feed', 'name' => 'POPSUGAR', 'category' => '10'),
        array('url' => 'https://www.usmagazine.com/feed/', 'name' => 'Us Weekly', 'category' => '10'),
        array('url' => 'https://www.eonline.com/feed', 'name' => 'E! Online', 'category' => '10'),
        array('url' => 'https://www.hollywoodlife.com/feed/', 'name' => 'Hollywood Life', 'category' => '10'),
        array('url' => 'https://www.lifeandstylemag.com/feed/', 'name' => 'Life & Style', 'category' => '10'),
        array('url' => 'https://www.cosmopolitan.com/rss/', 'name' => 'Cosmopolitan', 'category' => '10'),
        array('url' => 'https://www.hellomagazine.com/rss/', 'name' => 'HELLO!', 'category' => '10'),
        array('url' => 'https://www.teenvogue.com/feed/rss', 'name' => 'Teen Vogue', 'category' => '10'),
        array('url' => 'https://www.refinery29.com/rss.xml', 'name' => 'Refinery29', 'category' => '10'),
        array('url' => 'https://www.marieclaire.com/rss/', 'name' => 'Marie Claire', 'category' => '10'),
        array('url' => 'https://www.glamour.com/feed/rss', 'name' => 'Glamour', 'category' => '10'),
        array('url' => 'https://www.vogue.com/feed/rss', 'name' => 'Vogue', 'category' => '10'),
    ),
);

// =================================================================================================
// Logging
// =================================================================================================

function autocontent_articles_log($message) {
    echo '[' . date('Y-m-d H:i:s') . '] [pid ' . getmypid() . '] ' . $message . PHP_EOL;
    if (function_exists('ob_flush') && ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

// =================================================================================================
// Feed fetching / parsing
// =================================================================================================

function autocontent_articles_fetch($url) {
    $curl = curl_init($url);
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_NOSIGNAL => 1,
        // Many feeds are served gzip/deflate compressed; an empty encoding string lets
        // cURL negotiate and transparently decompress so the XML always reaches the parser.
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => AUTOCONTENT_ARTICLES_USER_AGENT,
        CURLOPT_HTTPHEADER => array('Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.8'),
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ));
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ($error ? ': ' . $error : ''));
    }
    if (strlen($body) > 5 * 1024 * 1024) {
        throw new RuntimeException('Feed larger than the 5MB safety limit.');
    }
    return $body;
}

function autocontent_articles_parse($xml) {
    if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
        // Block external entity expansion on PHP 7.x; PHP 8+ disables it by default.
        @libxml_disable_entity_loader(true);
    }
    $previous = libxml_use_internal_errors(true);
    $document = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if ($document === false) {
        throw new RuntimeException('Feed is not valid XML.');
    }

    $items = array();
    if (isset($document->channel->item)) {
        foreach ($document->channel->item as $item) {
            $items[] = $item;
        }
    } elseif (isset($document->entry)) {
        foreach ($document->entry as $item) {
            $items[] = $item;
        }
    } else {
        // RSS 1.0 / RDF keeps <item> at the document root.
        foreach ($document->item as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function autocontent_articles_text($value) {
    $value = (string) $value;
    if ($value === '') {
        return '';
    }
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(strip_tags($value));
}

function autocontent_articles_attr($element, $name) {
    // Namespaced elements need ->attributes(); array access returns empty for them.
    $attributes = $element->attributes();
    return isset($attributes[$name]) ? trim((string) $attributes[$name]) : '';
}

function autocontent_articles_image($item, $html) {
    $media_ns = 'http://search.yahoo.com/mrss/';
    $itunes_ns = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    // media:thumbnail / media:content
    $media = $item->children($media_ns);
    if (isset($media->thumbnail)) {
        foreach ($media->thumbnail as $thumb) {
            $url = autocontent_articles_attr($thumb, 'url');
            if ($url !== '') {
                return $url;
            }
        }
    }
    if (isset($media->content)) {
        foreach ($media->content as $content) {
            $url = autocontent_articles_attr($content, 'url');
            $type = strtolower(autocontent_articles_attr($content, 'type'));
            $medium = strtolower(autocontent_articles_attr($content, 'medium'));
            if ($url !== '' && ($medium === 'image' || strpos($type, 'image/') === 0 || $type === '')) {
                return $url;
            }
        }
    }
    // enclosure (RSS)
    if (isset($item->enclosure)) {
        foreach ($item->enclosure as $enclosure) {
            $url = autocontent_articles_attr($enclosure, 'url');
            $type = strtolower(autocontent_articles_attr($enclosure, 'type'));
            if ($url !== '' && (strpos($type, 'image/') === 0 || $type === '')) {
                return $url;
            }
        }
    }
    // itunes:image
    $itunes = $item->children($itunes_ns);
    if (isset($itunes->image)) {
        $url = autocontent_articles_attr($itunes->image, 'href');
        if ($url !== '') {
            return $url;
        }
    }
    // First <img> in the supplied HTML.
    if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $match)) {
        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function autocontent_articles_clean_html($html) {
    $html = (string) $html;
    $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
    $html = strip_tags($html, '<p><br><a><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><img><figure><figcaption><span>');
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|vbscript|data):[^"\']*\2/i', '$1="#"', $html);
    return trim($html);
}

function autocontent_articles_valid_image($url) {
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return '';
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, array('http', 'https'), true)) {
        return '';
    }
    return $url;
}

function autocontent_articles_item_date($item) {
    foreach (array('pubDate', 'published', 'updated', 'date') as $field) {
        if (!empty($item->{$field})) {
            $timestamp = strtotime((string) $item->{$field});
            if ($timestamp) {
                return $timestamp;
            }
        }
    }
    return time();
}

function autocontent_articles_categories($item) {
    $tags = array();
    if (isset($item->category)) {
        foreach ($item->category as $category) {
            $value = autocontent_articles_text($category);
            if ($value !== '') {
                $tags[] = $value;
            }
        }
    }
    return array_slice(array_unique($tags), 0, 8);
}

// =================================================================================================
// Main
// =================================================================================================

$summary = array('feeds' => 0, 'fetched' => 0, 'imported' => 0, 'duplicates' => 0, 'skipped' => 0, 'failures' => array());

try {
    $valid_categories = array_keys(get_object_vars($pt->categories));
    if (empty($valid_categories)) {
        $valid_categories = array('other');
    }

    autocontent_articles_log('=== Autocontent articles cron started ===');

    foreach ($autocontent_articles_feeds as $user_id => $feeds) {
        $user_id = (int) $user_id;
        $user = $db->where('id', $user_id)->getOne(T_USERS, array('id', 'username'));
        if (empty($user->id)) {
            $failure = 'CONFIGURATION FAILURE: user id ' . $user_id . ' does not exist; skipping its feeds.';
            $summary['failures'][] = $failure;
            autocontent_articles_log($failure);
            continue;
        }

        $seen_urls = array();
        foreach ($feeds as $feed) {
            $url = $feed['url'];
            $normalized = strtolower($url);
            if (isset($seen_urls[$normalized])) {
                continue;
            }
            $seen_urls[$normalized] = true;
            $summary['feeds']++;

            $category = isset($feed['category']) ? (string) $feed['category'] : 'other';
            if (!in_array($category, $valid_categories, true)) {
                $category = 'other';
            }
            $feed_name = !empty($feed['name']) ? $feed['name'] : parse_url($url, PHP_URL_HOST);

            autocontent_articles_log("--- @{$user->username} <- {$feed_name} ({$url}) ---");
            try {
                $items = autocontent_articles_parse(autocontent_articles_fetch($url));
            } catch (Exception $exception) {
                $summary['failures'][] = $feed_name . ': ' . $exception->getMessage();
                autocontent_articles_log('  feed failed: ' . $exception->getMessage());
                continue;
            }

            $summary['fetched'] += count($items);
            $imported_here = 0;
            foreach (array_slice($items, 0, AUTOCONTENT_ARTICLES_MAX_PER_FEED) as $item) {
                $title = autocontent_articles_text($item->title ?? '');
                if ($title === '') {
                    $summary['skipped']++;
                    continue;
                }
                $title = PT_ShortText($title, 250);
                $link = trim((string) ($item->link['href'] ?? $item->link ?? ''));
                if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
                    $link = '';
                }

                if ($db->where('title', PT_Secure($title))->where('user_id', $user_id)->getValue(T_POSTS, 'count(*)') > 0) {
                    $summary['duplicates']++;
                    continue;
                }

                $description_source = '';
                foreach (array('description', 'summary') as $field) {
                    if (!empty($item->{$field})) {
                        $description_source = (string) $item->{$field};
                        break;
                    }
                }
                if ($description_source === '') {
                    $description_source = (string) ($item->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? '');
                }
                $description = PT_ShortText(autocontent_articles_text($description_source), 190);

                $body_source = '';
                foreach (array('encoded', 'content', 'description', 'summary') as $field) {
                    $candidate = $field === 'encoded'
                        ? (string) ($item->children('http://purl.org/rss/1.0/modules/content/')->encoded ?? '')
                        : (string) ($item->{$field} ?? '');
                    if (trim($candidate) !== '') {
                        $body_source = $candidate;
                        break;
                    }
                }

                $image = autocontent_articles_valid_image(autocontent_articles_image($item, $body_source ?: $description_source));
                $clean_body = autocontent_articles_clean_html($body_source);
                if ($clean_body === '') {
                    $clean_body = '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
                }
                if ($link !== '') {
                    $clean_body .= '<p>Source: <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" rel="nofollow noopener" target="_blank">' . htmlspecialchars($feed_name, ENT_QUOTES, 'UTF-8') . '</a></p>';
                }

                $tags = autocontent_articles_categories($item);
                $tags[] = $feed_name;

                $insert_data = array(
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'image' => $image,
                    'text' => $clean_body,
                    'tags' => PT_ShortText(implode(',', array_unique($tags)), 250),
                    'time' => autocontent_articles_item_date($item),
                    'user_id' => $user_id,
                    'active' => 1,
                    'views' => 0,
                    'shared' => 0,
                );

                if ($db->insert(T_POSTS, $insert_data)) {
                    $summary['imported']++;
                    $imported_here++;
                } else {
                    $summary['failures'][] = $feed_name . ': database insert failed for "' . $title . '".';
                }
            }
            autocontent_articles_log("  {$feed_name}: items=" . count($items) . " imported={$imported_here}");
        }
    }

    autocontent_articles_log('--- Autocontent articles complete ---');
} catch (Exception $exception) {
    $summary['failures'][] = $exception->getMessage();
    autocontent_articles_log('FATAL: ' . $exception->getMessage());
}

autocontent_articles_log('=== Autocontent articles cron finished: imported ' . $summary['imported'] . ', duplicates ' . $summary['duplicates'] . ', skipped ' . $summary['skipped'] . ', failures ' . count($summary['failures']) . ' ===');

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
