SELECT * FROM {TABLE}
WHERE customer_id = :customer_id AND status != 'draft'
ORDER BY COALESCE(submitted_at, created_at) DESC, id DESC
