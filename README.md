# Trade Ordering

Trade pricing and ordering for trade customers. Builds on Trade Accounts (the customers) and the Osmium Shop (the products).

**Built so far:** a discount % and payment terms per customer, set on Trade Accounts' customer edit form (0.1), and a settings page for the brands that get the trade discount (0.2).

## How it adds to the customer form

Trade Accounts offers three hooks for extra customer fields. This service answers them, so Trade Accounts knows nothing about pricing:

- `trade.customer.fields`: the two fields (`ordering_discount_percent`, a number 0-100 in steps of 0.5, and `ordering_payment_terms`, a dropdown: Cash on Delivery, 7, 14, 30 or 60 Days) and each customer's saved values.
- `trade.customer.fields.validate`: refuses a discount outside 0-100 or terms not in the list.
- `trade.customer.fields.save`: stores them in `trade_customer_terms` (one row per customer; no row means no discount and no terms).

## Brands that get the discount

Under Services, **Trade Ordering** lists the shop's brands with a switch each. A brand switched off is left out of every customer's trade discount; new brands get the discount until switched off. The list comes from the shop's `shop.brands` hook (shop 0.39.0 or later), and only brand ids are stored here, in `trade_brand_exclusions`. A brand the shop stops listing keeps its exclusion in case it comes back.

## Planned

Trade pricing from `shop.product.get`, draft orders and submit, the Trade Orders admin, invoices. See the design note in the osmium-base knowledge base.

A customer deleted for good leaves a harmless terms row behind.
