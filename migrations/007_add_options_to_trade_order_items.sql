-- The options chosen on a line, as JSON for matching a repeat add and as text for showing. Both stay empty on a product with no options
ALTER TABLE `{PREFIX}trade_order_items`
  ADD COLUMN `options_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `product_title`,
  ADD COLUMN `options_display` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `options_json`;
