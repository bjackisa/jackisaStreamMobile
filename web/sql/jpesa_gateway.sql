-- JPesa asynchronous mobile-money transactions
CREATE TABLE IF NOT EXISTS `jpesa_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `merchant_transaction_id` varchar(100) NOT NULL,
  `amount` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) NOT NULL DEFAULT '',
  `status` enum('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `completed_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jpesa_transaction_id` (`transaction_id`),
  UNIQUE KEY `uq_jpesa_merchant_transaction_id` (`merchant_transaction_id`),
  KEY `idx_jpesa_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `config` (`name`, `value`) VALUES ('jpesa_payment', 'no')
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `config` (`name`, `value`) VALUES ('jpesa_currency', 'UGX')
ON DUPLICATE KEY UPDATE `name` = `name`;
