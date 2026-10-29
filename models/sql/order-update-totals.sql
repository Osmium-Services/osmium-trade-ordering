UPDATE {TABLE} AS O
SET
    O.item_count = (SELECT COALESCE(SUM(I.quantity), 0) FROM {PREFIX}trade_order_items AS I WHERE I.order_id = O.id),
    O.subtotal_exc_tax = (SELECT COALESCE(SUM(I.line_total_exc_tax), 0) FROM {PREFIX}trade_order_items AS I WHERE I.order_id = O.id),
    O.tax_amount = (SELECT COALESCE(SUM(I.line_total_inc_tax - I.line_total_exc_tax), 0) FROM {PREFIX}trade_order_items AS I WHERE I.order_id = O.id),
    O.total_inc_tax = (SELECT COALESCE(SUM(I.line_total_inc_tax), 0) FROM {PREFIX}trade_order_items AS I WHERE I.order_id = O.id)
WHERE O.id = :order_id
