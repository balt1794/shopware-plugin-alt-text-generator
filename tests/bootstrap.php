<?php declare(strict_types=1);

// Loads Shopware's classes from the shop's vendor folder and the plugin's own classes
// from src/, so the unit tests run without booting the whole kernel.
$root = getenv('SHOP_ROOT') ?: dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'AltTextGeneratorAI\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
