<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Register OCP/NCU namespaces from nextcloud/ocp package
// (the package does not declare autoload in its composer.json)
$ocpDir = __DIR__ . '/../vendor/nextcloud/ocp';
spl_autoload_register(function (string $class) use ($ocpDir): void {
    $prefixes = ['OCP\\', 'NCU\\'];
    foreach ($prefixes as $prefix) {
        if (str_starts_with($class, $prefix)) {
            $path = $ocpDir . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
            return;
        }
    }
});
