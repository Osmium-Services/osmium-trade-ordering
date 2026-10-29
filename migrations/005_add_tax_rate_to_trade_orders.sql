-- The tax rate in force when the order was submitted, so an invoice always shows the rate it was priced at
ALTER TABLE `{PREFIX}trade_orders` ADD COLUMN `tax_rate_percent` decimal(5,2) DEFAULT NULL AFTER `payment_terms`;
