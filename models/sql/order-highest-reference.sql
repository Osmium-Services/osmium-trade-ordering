SELECT MAX(reference) AS highest FROM {TABLE} WHERE reference LIKE :year_prefix AND status != 'draft'
