<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Controllers;

use Osmium\Core\Library\ServiceHooks;
use Osmium\Core\Library\ServiceLoader;
use Osmium\Core\Models\ServiceModel;
use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\TradeOrdering\Models\TradeBrandExclusions;
use Osmium\Services\TradeOrdering\Models\TradeBrandRules;

/**
 * Trade Ordering settings: which shop brands get the trade discount. A full-page form POST/redirect flow.
 *
 * Routes:
 *   - index() → /admin/settings/trade-ordering/  (GET shows the page; POST saves)
 */
class TradeOrderingSettingsController extends AdminController
{
    private const FLASH_KEYS = ['trade_ordering_saved', 'trade_ordering_error'];

    public function index(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) $this->save();

        $brands = $this->shopBrands();
        $exclusions = new TradeBrandExclusions($this->osmium->dataSource);

        $this->data['admin']['shopBrandsAvailable'] = $brands !== null;
        $this->data['admin']['brandRows'] = TradeBrandRules::rows(brands: $brands ?? [], excludedIds: $exclusions->ids());
        $this->data['admin']['settingsSaved'] = $_SESSION['trade_ordering_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['trade_ordering_error'] ?? false;
        foreach (self::FLASH_KEYS as $key) {
            unset($_SESSION[$key]);
        }

        $this->setView('trade-ordering-settings/index.phtml');
    }

    private function save(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) $this->fail('Invalid form submission. Please try again.');

        $brands = $this->shopBrands();
        $noBrands = $brands === null;
        if ($noBrands) $this->fail('The shop did not answer, so the brands could not be saved. Check the shop is installed and up to date.');

        $exclusions = new TradeBrandExclusions($this->osmium->dataSource);
        $ticked = \array_map('intval', (array) ($_POST['eligible'] ?? []));
        $exclusions->replace(TradeBrandRules::nextExclusions(brands: $brands, currentExcluded: $exclusions->ids(), tickedIds: $ticked));

        $this->admin->model->changelog->log(description: 'Updated Trade Ordering brand discount settings', recordType: 'settings');

        $_SESSION['trade_ordering_saved'] = true;
        $this->redirect('settings/trade-ordering/');
    }

    /** @return array<int, array{id: int, name: string}>|null null when the shop did not answer */
    private function shopBrands(): ?array
    {
        $hooks = new ServiceHooks(new ServiceLoader(new ServiceModel($this->osmium->dataSource)));

        return $hooks->handle('shop.brands', ['dataSource' => $this->osmium->dataSource]);
    }

    private function fail(string $message): never
    {
        $_SESSION['trade_ordering_error'] = $message;
        $this->redirect('settings/trade-ordering/');
    }
}
