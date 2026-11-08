<?php

declare(strict_types=1);

namespace App\Plugin\Development;

final class ShopwarePortabilityAnalyzer
{
    private const DIRECT_ADMIN_PATTERNS = [
        'Shopware.Module.register' => 'Companyware provides a compatible administration registration facade.',
        'Shopware.Component.register' => 'Companyware provides a compatible component registration facade.',
        'Shopware.Component.extend' => 'Companyware maps component extension to its versioned component registry.',
        'Shopware.Component.override' => 'Companyware maps explicit component overrides to version-checked replacement.',
        'Shopware.Service.register' => 'Companyware provides a small compatible administration service registry.',
        'Shopware.Application.addServiceProvider' => 'Companyware provides the familiar administration service-provider hook.',
    ];

    private const MANUAL_PATTERNS = [
        'Shopware\\Core\\Framework\\DataAbstractionLayer' => 'Map Shopware DAL entities and repositories to Companyware entity/repository contracts.',
        'Shopware\\Core\\Checkout' => 'Move checkout-specific behavior into a Companyware commerce plugin contract.',
        'Shopware\\Core\\Content' => 'Map Shopware content services to the owning Companyware domain plugin.',
        'Shopware\\Core\\System\\SystemConfig' => 'Map system configuration to PluginConfigurationInterface.',
        'Shopware\\Storefront' => 'Port storefront templates and subscribers to public Companyware frontend routes and themes.',
        'shopware.repositoryFactory' => 'Replace the Administration DAL repository factory with the versioned Companyware HTTP API.',
        'shopware:composables' => 'Replace Shopware virtual-module composables with Companyware SDK services.',
        'shopware:data' => 'Replace Shopware Criteria/DAL helpers with Companyware API filters and pagination.',
    ];

    public function analyze(string $directory): array
    {
        if (!is_dir($directory)) throw new \InvalidArgumentException(sprintf('Directory does not exist: %s', $directory));
        $direct = [];
        $manual = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'ts', 'vue', 'twig', 'xml'], true)) continue;
            $contents = (string) file_get_contents($file->getPathname());
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($directory, '/\\')) + 1));
            $this->collect($contents, $relative, self::DIRECT_ADMIN_PATTERNS, $direct);
            $this->collect($contents, $relative, self::MANUAL_PATTERNS, $manual);
        }

        return [
            'format' => 'companyware-shopware-portability-1',
            'source' => realpath($directory) ?: $directory,
            'directAdministrationMappings' => array_values($direct),
            'manualMappings' => array_values($manual),
            'summary' => [
                'direct' => count($direct),
                'manual' => count($manual),
                'administrationPortable' => $manual === [],
            ],
        ];
    }

    private function collect(string $contents, string $file, array $patterns, array &$result): void
    {
        foreach ($patterns as $pattern => $guidance) {
            if (!str_contains($contents, $pattern)) continue;
            $key = $pattern.'@'.$file;
            $result[$key] = ['pattern' => $pattern, 'file' => $file, 'guidance' => $guidance];
        }
    }
}
