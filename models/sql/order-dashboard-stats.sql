-- The dashboard tiles: open baskets (drafts with something in them) and what was submitted in the last 24 hours.
-- Drafts are baskets, not orders. No customer data.
SELECT
    COALESCE(SUM(status = 'draft' AND item_count > 0), 0) AS active_count,
    COALESCE(SUM(CASE WHEN status = 'draft' AND item_count > 0 THEN total_inc_tax END), 0) AS active_value,
    COALESCE(SUM(status NOT IN ('draft', 'cancelled') AND submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)), 0) AS converted_count,
    COALESCE(SUM(CASE WHEN status NOT IN ('draft', 'cancelled') AND submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN total_inc_tax END), 0) AS converted_value
FROM {TABLE}
WHERE status = 'draft' OR submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
