-- Submitted order counts and values (inc VAT) per status for today, the last 7 days and the last 30 days,
-- by submitted date. Drafts are baskets, not orders, so they are left out. No customer data.
SELECT
    status,
    SUM(submitted_at >= CURDATE()) AS today_count,
    COALESCE(SUM(CASE WHEN submitted_at >= CURDATE() THEN total_inc_tax END), 0) AS today_value,
    SUM(submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)) AS week_count,
    COALESCE(SUM(CASE WHEN submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) THEN total_inc_tax END), 0) AS week_value,
    COUNT(*) AS month_count,
    COALESCE(SUM(total_inc_tax), 0) AS month_value
FROM {TABLE}
WHERE status <> 'draft'
  AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
GROUP BY status
