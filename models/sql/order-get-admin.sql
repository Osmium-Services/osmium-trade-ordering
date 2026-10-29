SELECT O.*, C.company_name, C.contact_name, C.email, C.phone, C.address_line_1, C.address_line_2, C.city, C.county, C.postcode, C.country
FROM {TABLE} AS O
LEFT JOIN {PREFIX}trade_customers AS C ON C.id = O.customer_id
WHERE O.id = :id
LIMIT 1
