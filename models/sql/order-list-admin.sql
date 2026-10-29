SELECT O.*, C.company_name, C.contact_name, C.email
FROM {TABLE} AS O
LEFT JOIN {PREFIX}trade_customers AS C ON C.id = O.customer_id
WHERE FIND_IN_SET(O.status, :statuses) > 0
ORDER BY COALESCE(O.submitted_at, O.created_at) DESC, O.id DESC
