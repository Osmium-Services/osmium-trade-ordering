-- A trade customer's own pricing terms. Kept here, not on Trade Accounts' customer, so Trade Accounts stays free of
-- anything about pricing. One row per customer; no row means no discount and no terms.
CREATE TABLE IF NOT EXISTS `{PREFIX}trade_customer_terms` (
  `customer_id` int unsigned NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Percent off eligible products, 0 to 100',
  `payment_terms` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
