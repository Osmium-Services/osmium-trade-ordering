# Trade Ordering

Trade pricing and ordering for trade customers. Builds on Trade Accounts (the customers) and the Osmium Shop (the products).

**Built so far (0.1.0):** a discount % and payment terms per customer, set on Trade Accounts' customer edit form.

## How it adds to the customer form

Trade Accounts offers three hooks for extra customer fields. This service answers them, so Trade Accounts knows nothing about pricing:

- `trade.customer.fields`: the two fields (`ordering_discount_percent`, a number 0-100 in steps of 0.5, and `ordering_payment_terms`, a dropdown: Cash on Delivery, 7, 14, 30 or 60 Days) and each customer's saved values.
- `trade.customer.fields.validate`: refuses a discount outside 0-100 or terms not in the list.
- `trade.customer.fields.save`: stores them in `trade_customer_terms` (one row per customer; no row means no discount and no terms).

## Planned

Brands excluded from the trade discount, trade pricing from `shop.product.get`, draft orders and submit, the Trade Orders admin, invoices. See the design note in the osmium-base knowledge base.

A customer deleted for good leaves a harmless terms row behind.
