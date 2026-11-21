-- The orders still open (pending, confirmed, processing, shipped): how many, how many are waiting to be confirmed,
-- and how long the oldest of those has waited. No customer data.
SELECT
    SUM(status IN ('pending', 'confirmed', 'processing', 'shipped')) AS open_orders,
    SUM(status = 'pending') AS pending_orders,
    MIN(CASE WHEN status = 'pending' THEN submitted_at END) AS oldest_pending_at,
    TIMESTAMPDIFF(DAY, MIN(CASE WHEN status = 'pending' THEN submitted_at END), NOW()) AS oldest_pending_days
FROM {TABLE}
WHERE status <> 'draft'
