UPDATE {TABLE}
SET reference = :reference, po_reference = :po_reference, payment_terms = :payment_terms, status = 'pending', submitted_at = NOW()
WHERE id = :order_id AND status = 'draft'
