INSERT INTO {TABLE} (customer_id, discount_percent, payment_terms)
VALUES (:customer_id, :discount_percent, :payment_terms)
ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), payment_terms = VALUES(payment_terms)
