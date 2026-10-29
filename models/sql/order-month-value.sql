SELECT COALESCE(SUM(total_inc_tax), 0) AS total, COUNT(*) AS orders
FROM {TABLE}
WHERE status NOT IN ('draft', 'cancelled') AND submitted_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
