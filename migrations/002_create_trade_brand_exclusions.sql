-- Brands that get no trade discount. A brand not listed here is eligible. Holds the shop's brand id only, so the shop
-- stays free of anything about trade.
CREATE TABLE IF NOT EXISTS `{PREFIX}trade_brand_exclusions` (
  `brand_id` smallint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
