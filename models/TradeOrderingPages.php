<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

use Osmium\Core\Library\AdminPaths;
use Osmium\Modules\Admin\Models\Page;
use Osmium\Modules\Admin\Services\AdminPages;

/**
 * Keeps the trade ordering pages in step with the service, as Trade Accounts does for its pages: created when the
 * service is installed, put in the bin (never removed) when it is disabled or removed, restored when enabled again.
 * They are ordinary Pages rows; TradeOrderingClaim answers for them.
 */
class TradeOrderingPages
{
    public const PRODUCTS = 'trade/products';
    public const BASKET = 'trade/basket';
    public const ORDERS = 'trade/orders';

    /** @var array<string, string> url => page title */
    public const PAGES = [
        self::PRODUCTS => 'Trade Products',
        self::BASKET => 'Trade Order',
        self::ORDERS => 'Trade Orders',
    ];

    public function __construct(private Page $page, private AdminPages $pages)
    {
    }

    /** @return string[] */
    public static function urls(): array
    {
        return \array_keys(self::PAGES);
    }

    /** pages.protected_urls: pages no page tool may delete or move */
    public static function protectedUrls(array $payload): array
    {
        return self::urls();
    }

    /** service.installed and service.enabled */
    public static function onEnabled(array $payload): void
    {
        self::forDatabase($payload['db'])->sync(true);
    }

    /** service.disabled and service.removing */
    public static function onDisabled(array $payload): void
    {
        self::forDatabase($payload['db'])->sync(false);
    }

    public function sync(bool $on): void
    {
        $rows = [];
        foreach ($this->page->getAll() as $existing) {
            $rows[$existing['url']] = $existing;
        }

        foreach (self::PAGES as $url => $title) {
            $row = $rows[$url] ?? null;
            $isMissing = $row === null;
            $isInBin = !$isMissing && $row['deleted_at'] !== null;

            if ($on && $isMissing) $this->create($url, $title);
            if ($on && $isInBin) $this->page->restore((int) $row['id']);
            if (!$on && !$isMissing && !$isInBin) $this->page->softDelete((int) $row['id'], \date('Y-m-d H:i:s'));
        }

        $this->pages->regenerateRoutesFile();
    }

    private static function forDatabase(\Osmium\Core\Library\OsmiumPDO $db): self
    {
        require_once AdminPaths::path('services/AdminPages.php');
        $page = new Page($db, 'pages');

        return new self($page, new AdminPages($page));
    }

    private function create(string $url, string $title): void
    {
        $this->page->insert(
            id: $this->page->getNextId(),
            parentPageId: null,
            url: $url,
            title: $title,
            description: null,
            robots: 'noindex,nofollow',
            now: \date('Y-m-d H:i:s'),
        );
    }
}
