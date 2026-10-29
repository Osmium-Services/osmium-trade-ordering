-- The lines of a trade order. The product's name, reference and the prices are copied on when the line is added, so an
-- order never changes when the shop does.
CREATE TABLE IF NOT EXISTS `{PREFIX}trade_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `product_ref` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price_exc_tax` decimal(10,2) NOT NULL,
  `unit_price_inc_tax` decimal(10,2) NOT NULL,
  `line_total_exc_tax` decimal(10,2) NOT NULL,
  `line_total_inc_tax` decimal(10,2) NOT NULL,
  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_{PREFIX}trade_order_items_order` (`order_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
