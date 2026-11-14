-- How the trade price list treats a shop category. A category not listed here is shown as its own section. Holds the
-- shop's category id only, so the shop stays free of anything about trade. Mode is inside (shown inside its parent's
-- section) or hidden (its products are left off the list).
CREATE TABLE IF NOT EXISTS `{PREFIX}trade_category_rules` (
  `category_id` int unsigned NOT NULL,
  `mode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
