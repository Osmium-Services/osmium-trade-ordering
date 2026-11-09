# Trade Ordering Service

Status (2026-10-29): **built and installed on dev, v0.7.2**; no theme views yet. The ordering half of SolarGo2's `trade`
module, as a service with id `osmium-trade-ordering` (repo `Osmium-Services/osmium-trade-ordering`, category business).
Needs [[Trade Accounts Service (design)|Trade Accounts]] and `osmium-shop`. Part of [[Osmium Architecture]]. Nothing here
calls another service's PHP: the shop is read through hooks, Trade Accounts through hooks and SQL.

SolarGo2's trade module is itself unfinished (no trade checkout or payment). So parity is not the finish line: build as far
as possible without the business lead, and park anything that needs a business decision (checkout, payment) until then.

## What SolarGo2 does (the reference)

- **Own tables, not the shop's orders.** `trade_orders` and `trade_order_items`. A customer builds a **draft** order, adds
  products, then submits it.
- **Submit** gives it a reference `TO-YYYY-NNNN` (next number for the year), stores an optional customer PO reference, and
  copies the customer's payment terms onto the order. Status becomes `pending`.
- **Statuses:** draft, pending, confirmed, processing, shipped, delivered, cancelled.
- **Pricing:** trade price = shop price ex tax, less the customer's discount %, when the brand allows a trade discount.
  Option premiums are added after and are never discounted. Totals are summed from the lines.
- **Customer pages:** product list, basket, order history, invoice PDF, documents and image downloads.
- **Admin:** Trade Orders list, detail, status change; dashboard widget. SolarGo2 sends **no order emails**.

## What is built

| Part | Detail |
|---|---|
| **Customer terms** (0.1) | Discount % (0 to 100, step 0.5) and a payment terms dropdown (COD, 7, 14, 30, 60 Days) on Trade's customer edit form, through the `trade.customer.fields*` hooks. Stored in `trade_customer_terms` (customer id as key), not as Trade Accounts columns |
| **Brand exclusions** (0.2) | Settings > Trade Ordering: a switch per shop brand, off means no trade discount. Brands come from `shop.brands`. Stored in `trade_brand_exclusions` (brand ids only); exclusions of brands the shop no longer lists are kept |
| **Pricing and basket** (0.3) | `TradePricing` (SolarGo2's rule, each figure rounded to pence on its own, reference `TO-YYYY-NNNN`), `TradeOrderStatus`, `TradeOrder`, `TradeOrderItem`, `TradeBasket` (add merges the same product, a quantity under 1 removes the line, maximum 9999, submit copies payment terms and the tax rate, retries the reference on a unique-key clash). Tables `trade_orders`, `trade_order_items`. **Nothing calls `TradeBasket` yet**: its constructor takes callables for the shop product, customer terms, excluded brands and tax rate, to be wired to the real ones with its first caller |
| **Admin Trade Orders** (0.4) | Top-level menu item. Open orders, then a collapsed Delivered and Cancelled list; row actions Confirm, Mark processing, Mark shipped, Mark delivered, Cancel, Reopen as pending; View details modal; a changelog entry per status change. A draft is never listed or changed here. The list joins `trade_customers` in SQL for the name and email |
| **Dashboard card** (0.5) | `dashboard.widgets`: pending count, this month's value (drafts and cancelled left out) and the first five pending orders |
| **Order PDF** (0.6) | Admin "Download PDF" on its own TCPDF layout. Title follows the status: Order Acknowledgement (pending), Order Confirmation (confirmed, processing), Invoice (shipped, delivered). None for drafts or cancelled. No logo (SolarGo2's was hard-coded). Migration 005 keeps `tax_rate_percent` on the order at submit, so an invoice shows the rate it was priced at |
| **Emails** (0.7, not in SolarGo2) | Order confirmation and dispatch confirmation to the customer, in the site's email styling with the logo (core `BrandLogo`) and the customer's company name. Dispatch sends from the admin when an order is marked Shipped; confirmation is sent by `TradeBasket::submit` through its `afterSubmit` callback, which the customer pages connect. A failed send is logged and never blocks. Both, and Trade Accounts' two login emails, are in the Email settings test list through `email.samples` |

## Decisions (Gavin, 2026-10-29)

- **The shop stays trade-free.** The old per-brand `trade_discount_allowed` column was dropped from the shop (migration 011).
  Trade Ordering keeps its own list of excluded brands.
- **Phase 1 is plain products only.** Configured products (options and their never-discounted premiums) come later, once the
  plain flow works; they need a second shop hook.
- **Trade Ordering reads the shop only through hooks:** `shop.product.get` (id, brand id and name, name, reference, ex-tax price,
  availability) and `shop.brands`. Prices are ex tax; the tax rate stays in Shop settings.
- **Invoice payment terms fall back to 30 Days** when the order has none. A customer with no terms stores none; only the printed
  document and the emails show 30 Days, and order history shows "Not specified".
- **Own invoice layout on the site's TCPDF**, not the shop's `ShopOrderPdfService`: trade documents differ (PO reference, payment
  terms, discount), and a service must not reach into the shop's PHP.
- **Documents and image downloads are a separate service, Trade Downloads** (`osmium-trade-downloads`, later). The files belong to
  the shop; Trade Downloads groups products by the shop's categories, serves the PDFs and builds the image ZIP. It needs one
  read-only shop hook listing a product's document and image files. The SolarGo2 rebuild uses it too.
- **Statuses and who changes them:** an admin can move a submitted order to any status other than its current one (so a mistake can
  be fixed); never a draft, never to a made-up status.

## Data

| Table | Holds |
|---|---|
| `trade_orders` | customer id, reference, status, PO reference, discount % and payment terms and tax rate as at submit, totals, timestamps. Drafts carry a placeholder reference until submitted |
| `trade_order_items` | order id, product id, name, reference, quantity, unit and line prices (ex and inc tax). Name and price are copied at add time so an order never changes when the shop does |
| `trade_customer_terms` | customer id, discount %, payment terms |
| `trade_brand_exclusions` | brand ids that get no trade discount |

## Left to build

1. **Customer pages in the theme** (wait for the SolarGo2 rebuild): product list, basket, order history, the customer's own PDF
   download. Claimed through `page.claim` like Trade Accounts' pages. This is where `TradeBasket` is wired to the real shop, terms,
   exclusions and tax rate, and where `afterSubmit` sends the confirmation. It asks Trade Accounts' `trade.customer.current` (built, 0.7.0) "who is logged in?" without calling Trade's PHP.
2. **SolarGo2 data migration: not on the plan.** Whether to bring SolarGo2's data across at all is undecided (Gavin questioned it
   2026-10-29; it had been an assumption). If it is wanted later, the mapping would be `lacrm_contact_id` to `source = 'lacrm'` plus
   `source_ref`, discount and payment terms to `trade_customer_terms`, brand flags to `trade_brand_exclusions`, and the existing orders.
3. **Trade checkout and payment:** SolarGo2 has none either. Needs the business lead.
4. ~~Trade Downloads and its shop hook~~: built, see [[Trade Downloads Service]].

**Parked:** attaching the order PDF to the emails. Core's `MailerInterface::send(recipients, subject, htmlBody)` has no
attachments and neither do the mail provider services, so it needs a core change (a rollout question) plus each provider.
The emails say an invoice is available from the trade account.

## Things learned building it

See [[Lessons Learned]]: migration semicolons, model constructors, admin JSON `csrf_token`, email styling, and scratch scripts.
