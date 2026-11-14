INSERT INTO {TABLE} (order_id, product_id, product_ref, product_title, options_json, options_display, quantity, unit_price_exc_tax, unit_price_inc_tax, line_total_exc_tax, line_total_inc_tax)
VALUES (:order_id, :product_id, :product_ref, :product_title, :options_json, :options_display, :quantity, :unit_exc, :unit_inc, :line_exc, :line_inc)
