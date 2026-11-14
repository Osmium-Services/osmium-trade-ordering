<?php

declare(strict_types=1);

namespace Osmium\Services\TradeOrdering\Models;

/**
 * Trade Ordering's own settings file (app/config/services/osmium-trade-ordering.json.php): whether the trade discount also
 * comes off an option's extra price. Off unless switched on, so an option costs what the shop says.
 */
final class TradeOrderingConfig
{
    public const PATH = 'app/config/services/osmium-trade-ordering.json.php';

    /** @return array{discountOptions: bool} */
    public static function get(string $path = self::PATH): array
    {
        $defaults = ['discountOptions' => false];

        $content = \file_exists($path) ? (string) \file_get_contents($path) : '';
        $jsonStart = \strpos($content, '{');
        if ($jsonStart === false) return $defaults;

        $decoded = \json_decode(\substr($content, $jsonStart), associative: true);
        $saved = \is_array($decoded) ? ($decoded['tradeOrdering'] ?? []) : [];

        return ['discountOptions' => (bool) ($saved['discountOptions'] ?? false)];
    }

    public static function save(bool $discountOptions, string $path = self::PATH): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir)) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $json = \json_encode(['tradeOrdering' => ['discountOptions' => $discountOptions]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        \file_put_contents($path, "<?php exit(); ?>\n" . $json . "\n");
    }
}
