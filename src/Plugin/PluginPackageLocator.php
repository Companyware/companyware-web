<?php

declare(strict_types=1);

namespace App\Plugin;

use Composer\InstalledVersions;

final class PluginPackageLocator
{
    public function __construct(private readonly string $projectDir)
    {
    }

    /** @return list<string> */
    public function directories(): array
    {
        $directories = glob($this->projectDir.'/plugins/*', GLOB_ONLYDIR) ?: [];
        if (class_exists(InstalledVersions::class) && method_exists(InstalledVersions::class, 'getInstalledPackagesByType')) {
            foreach (InstalledVersions::getInstalledPackagesByType('companyware-plugin') as $package) {
                $path = InstalledVersions::getInstallPath($package);
                if (is_string($path)) {
                    $directories[] = $path;
                }
            }
        }

        $unique = [];
        foreach ($directories as $directory) {
            $path = realpath($directory);
            if ($path && is_file($path.'/plugin.json')) {
                $unique[$path] = $path;
            }
        }

        ksort($unique);
        return array_values($unique);
    }
}
