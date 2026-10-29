UPDATE {TABLE}
SET reference = :reference, po_reference = :po_reference, payment_terms = :payment_terms, tax_rate_percent = :tax_rate, status = 'pending', submitted_at = NOW()
WHERE id = :order_id AND status = 'draft'
