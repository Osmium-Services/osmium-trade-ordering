# Trade Ordering

Trade pricing and ordering for trade customers. Builds on Trade Accounts (the customers) and the Osmium Shop (the products).

**Built so far:** a discount % and payment terms per customer, set on Trade Accounts' customer edit form (0.1), a settings page for the brands that get the trade discount (0.2), and the pricing and basket rules with the order tables (0.3, no pages yet).

## How it adds to the customer form

Trade Accounts offers three hooks for extra customer fields. This service answers them, so Trade Accounts knows nothing about pricing:

- `trade.customer.fields`: the two fields (`ordering_discount_percent`, a number 0-100 in steps of 0.5, and `ordering_payment_terms`, a dropdown: Cash on Delivery, 7, 14, 30 or 60 Days) and each customer's saved values.
- `trade.customer.fields.validate`: refuses a discount outside 0-100 or terms not in the list.
- `trade.customer.fields.save`: stores them in `trade_customer_terms` (one row per customer; no row means no discount and no terms).

## Brands that get the discount

Under Services, **Trade Ordering** lists the shop's brands with a switch each. A brand switched off is left out of every customer's trade discount; new brands get the discount until switched off. The list comes from the shop's `shop.brands` hook (shop 0.39.0 or later), and only brand ids are stored here, in `trade_brand_exclusions`. A brand the shop stops listing keeps its exclusion in case it comes back.

## Pricing and orders (0.3)

`TradePricing` works out a line the way SolarGo2 does: the customer's discount comes off the shop's ex-tax price when the brand is eligible, tax is added, and each figure is rounded to pence on its own. `TradeBasket` holds the steps: add a product (the same product again adds to its line), change a quantity (under 1 removes the line), remove a line, and submit. Submit gives the order a reference such as `TO-2026-0007`, an optional PO reference, and copies the customer's payment terms on. A draft is the customer's basket, one per customer, in `trade_orders` and `trade_order_items`. Lines copy the product's name, reference and prices when added, so a later shop change never alters an order.

Nothing calls this yet: the pages and the admin come next, and the basket is wired to the real shop, terms, exclusions and tax rate when its first caller is built.

## Trade Orders admin (0.4)

Trade Orders in the admin menu lists submitted orders (open ones, then a collapsed list of delivered and cancelled), with the customer's name from Trade Accounts. Row actions move an order along: Confirm, Mark processing, Mark shipped, Mark delivered, Cancel (and Reopen as pending for a cancelled one). View details shows the customer, PO reference, payment terms, discount and the lines. Each status change goes in the changelog. A draft (the customer's basket) is never listed or changed here.

## Planned

The dashboard widget, invoices, order emails, and the customer pages from the theme. See the design note in the osmium-base knowledge base.

A customer deleted for good leaves a harmless terms row behind.
