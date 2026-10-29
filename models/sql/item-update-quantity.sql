UPDATE {TABLE}
SET quantity = :quantity, line_total_exc_tax = ROUND(unit_price_exc_tax * :quantity, 2), line_total_inc_tax = ROUND(unit_price_inc_tax * :quantity, 2)
WHERE id = :item_id AND order_id = :order_id
