-- The newest submitted orders with no customer details: reference, status, money, terms and dates only.
SELECT reference, status, item_count, total_inc_tax, payment_terms, submitted_at
FROM {TABLE}
WHERE status <> 'draft'
  AND submitted_at IS NOT NULL
ORDER BY submitted_at DESC, id DESC
LIMIT :limit
