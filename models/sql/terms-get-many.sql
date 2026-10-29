SELECT customer_id, discount_percent, payment_terms FROM {TABLE} WHERE FIND_IN_SET(customer_id, :ids) > 0
