<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\TradeOrdering\Models\TradeInvoiceLayout;
use Osmium\Services\TradeOrdering\Models\TradeInvoicePdf;
use Osmium\Services\TradeOrdering\Models\TradeOrder;
use Osmium\Services\TradeOrdering\Models\TradeOrderItem;
use Osmium\Services\TradeOrdering\Models\TradeOrderMailer;
use Osmium\Services\TradeOrdering\Models\TradeOrderStatus;

/**
 * Trade orders admin.
 *
 * Routes:
 *   - index()  → /admin/trade/orders/
 *   - action() → /admin/trade/orders/action/   (AJAX: order details and status changes, and the PDF download)
 */
class TradeOrdersController extends AdminController
{
    private const RECORD_TYPE = 'trade_order';

    public function index(): void
    {
        $model = new TradeOrder($this->osmium->dataSource);
        $sign = $this->currencySign();
        $open = $model->listForAdmin(TradeOrderStatus::OPEN);
        $closed = $model->listForAdmin(TradeOrderStatus::CLOSED);

        $this->data['admin']['lists'] = require __DIR__ . '/../lists/trade-orders.php';
        $this->data['admin']['openOrders'] = \array_map(fn(array $row): array => $this->formatOrder(row: $row, sign: $sign), $open);
        $this->data['admin']['closedOrders'] = \array_map(fn(array $row): array => $this->formatOrder(row: $row, sign: $sign), $closed);

        $this->setView('trade-orders/index.phtml');
    }

    public function action()
    {
        $isDownload = ($_POST['action'] ?? '') === 'download_invoice';
        if ($isDownload) $this->downloadInvoice();

        \header('Content-Type: application/json');

        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $action = (string) ($input['action'] ?? '');
        $id = (int) ($input['id'] ?? 0);

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $missingParams = $action === '' || !$id;
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = $this->run(action: $action, id: $id);
        } catch (\InvalidArgumentException $e) {
            $this->admin->jsonError($e->getMessage());
        }

        $this->admin->jsonSuccess($result);
    }

    /** Streams the order's PDF. It is a form post, so a failure is a plain-text page rather than JSON. */
    private function downloadInvoice(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        $csrfValid = $isPost && $this->admin->auth->validateCsrfJson($_POST);
        if (!$csrfValid) $this->plainError(code: 400, message: 'Invalid request token. Please go back, refresh and try again.');

        $order = (new TradeOrder($this->osmium->dataSource))->getForAdmin((int) ($_POST['id'] ?? 0));
        $document = $order === null ? null : TradeInvoiceLayout::document((string) $order['status']);
        if ($document === null) $this->plainError(code: 404, message: 'This order has no document');

        $config = $this->osmium->config;
        $taxName = (string) ($config->shop->taxName ?? 'VAT');
        $items = (new TradeOrderItem($this->osmium->dataSource))->listByOrder((int) $order['id']);
        $pdf = (new TradeInvoicePdf())->build(
            order: $order,
            items: $items,
            companyLines: TradeInvoiceLayout::companyLines(company: (array) ($config->company ?? []), taxName: $taxName),
            siteName: (string) ($config->site->name ?? ''),
            sign: $this->currencySign(),
            taxName: $taxName,
        );

        $filename = TradeInvoiceLayout::filename(prefix: $document['prefix'], reference: (string) $order['reference']);
        \header('Content-Type: application/pdf');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        \header('Content-Length: ' . \strlen($pdf));
        \header('Cache-Control: private, max-age=0, must-revalidate');
        echo $pdf;
        exit;
    }

    private function plainError(int $code, string $message): never
    {
        \http_response_code($code);
        \header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }

    /** @return array<string, mixed> */
    private function run(string $action, int $id): array
    {
        $isStatus = \str_starts_with($action, 'status_');
        if ($isStatus) return $this->changeStatus(id: $id, status: \substr($action, 7));
        if ($action === 'details') return $this->details($id);

        throw new \InvalidArgumentException('Unknown action');
    }

    /** @return array<string, mixed> */
    private function changeStatus(int $id, string $status): array
    {
        $model = new TradeOrder($this->osmium->dataSource);
        $order = $model->getForAdmin($id);
        if (!$order) throw new \InvalidArgumentException('Order not found');

        $allowed = TradeOrderStatus::adminCanSet(current: (string) $order['status'], new: $status);
        if (!$allowed) throw new \InvalidArgumentException('That status change is not allowed');

        $model->setStatus(id: $id, status: $status);
        $this->admin->model->changelog->log(
            description: "Updated trade order {$order['reference']} status to '{$status}' ({$order['company_name']})",
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );

        $dispatched = $status === TradeOrderStatus::SHIPPED;
        if ($dispatched) (new TradeOrderMailer($this->osmium))->sendDispatched($id);

        return ['success' => true, 'id' => $id];
    }

    /** @return array<string, mixed> */
    private function details(int $id): array
    {
        $model = new TradeOrder($this->osmium->dataSource);
        $order = $model->getForAdmin($id);
        if (!$order) throw new \InvalidArgumentException('Order not found');

        $sign = $this->currencySign();
        $items = (new TradeOrderItem($this->osmium->dataSource))->listByOrder($id);
        $discount = (float) $order['discount_percent'];
        $submitted = $order['submitted_at'] ? \date('j M Y, g:ia', \strtotime((string) $order['submitted_at'])) : 'Not submitted';

        return [
            'success' => true,
            'order' => [
                'reference' => $order['reference'],
                'statusLabel' => TradeOrderStatus::label((string) $order['status']),
                'statusClass' => TradeOrderStatus::badgeClass((string) $order['status']),
                'company' => $order['company_name'] ?? '-',
                'contact' => $order['contact_name'] ?? '',
                'email' => $order['email'] ?? '',
                'phone' => $order['phone'] ?? '',
                'submitted' => $submitted,
                'poReference' => $order['po_reference'] ?? '',
                'paymentTerms' => $order['payment_terms'] ?? 'Not specified',
                'discount' => $discount > 0 ? $discount . '%' : 'None',
                'subtotal' => $this->money(sign: $sign, amount: $order['subtotal_exc_tax']),
                'tax' => $this->money(sign: $sign, amount: $order['tax_amount']),
                'total' => $this->money(sign: $sign, amount: $order['total_inc_tax']),
            ],
            'items' => \array_map(fn(array $item): array => [
                'reference' => $item['product_ref'],
                'title' => $item['product_title'],
                'quantity' => (int) $item['quantity'],
                'unit' => $this->money(sign: $sign, amount: $item['unit_price_exc_tax']),
                'line' => $this->money(sign: $sign, amount: $item['line_total_exc_tax']),
            ], $items),
        ];
    }

    /** @return array<string, mixed> */
    private function formatOrder(array $row, string $sign): array
    {
        $when = $row['submitted_at'] ?? $row['created_at'];

        return [
            'id' => (int) $row['id'],
            'reference' => (string) $row['reference'],
            'status' => (string) $row['status'],
            'statusLabel' => TradeOrderStatus::label((string) $row['status']),
            'statusClass' => TradeOrderStatus::badgeClass((string) $row['status']),
            'company_name' => $row['company_name'] ?? null,
            'email' => $row['email'] ?? '',
            'item_count' => (int) $row['item_count'],
            'totalText' => $this->money(sign: $sign, amount: $row['total_inc_tax']),
            'submittedText' => \date('j M Y, g:ia', \strtotime((string) $when)),
        ];
    }

    private function money(string $sign, mixed $amount): string
    {
        return $sign . \number_format((float) $amount, 2);
    }

    private function currencySign(): string
    {
        return (string) ($this->osmium->config->shop->currencySign ?? '£');
    }
}
