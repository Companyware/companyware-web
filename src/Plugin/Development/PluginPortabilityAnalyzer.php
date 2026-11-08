<?php

declare(strict_types=1);

namespace App\Plugin\Development;

final class PluginPortabilityAnalyzer
{
    /** @var list<string> */
    private const PLATFORM_NAMESPACES = ['Companyware', 'Symfony', 'Plugins', 'Psr', 'Twig'];

    public function analyze(string $directory): array
    {
        if (!is_dir($directory)) throw new \InvalidArgumentException(sprintf('Directory does not exist: %s', $directory));
        $allowedRoots = $this->allowedNamespaceRoots($directory);
        $transformations = [];
        $manual = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'ts', 'vue', 'twig', 'xml'], true)) continue;
            $contents = (string) file_get_contents($file->getPathname());
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($directory, '/\\')) + 1));
            $this->collectAdministrationRegistrations($contents, $relative, $transformations);
            $this->collectExternalPhpImports($contents, $relative, $allowedRoots, $manual);
            $this->collectExternalFrontendApis($contents, $relative, $manual);
        }

        return [
            'format' => 'companyware-portability-1',
            'source' => realpath($directory) ?: $directory,
            'transformations' => array_values($transformations),
            'manualMappings' => array_values($manual),
            'summary' => [
                'transformable' => count($transformations),
                'manual' => count($manual),
                'readyForNativeValidation' => $manual === [],
            ],
        ];
    }

    /** @return list<string> */
    private function allowedNamespaceRoots(string $directory): array
    {
        $roots = self::PLATFORM_NAMESPACES;
        $file = $directory.'/composer.json';
        $composer = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        foreach (($composer['autoload']['psr-4'] ?? []) as $namespace => $_path) {
            if (is_string($namespace) && preg_match('/^([A-Z][A-Za-z0-9_]*)\\\\/', $namespace, $match)) $roots[] = $match[1];
        }
        return array_values(array_unique($roots));
    }

    private function collectAdministrationRegistrations(string $contents, string $file, array &$result): void
    {
        preg_match_all('/\b([A-Z][A-Za-z0-9_]*)\.(Module|Component|Service|Application)\.(register|extend|override|addServiceProvider)\b/', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $pattern = $match[0];
            $result[$pattern.'@'.$file] = [
                'pattern' => $pattern,
                'file' => $file,
                'target' => 'window.CompanywareAdmin',
                'guidance' => 'Convert the external administration registration to the native versioned Companyware registry.',
            ];
        }
    }

    private function collectExternalPhpImports(string $contents, string $file, array $allowedRoots, array &$result): void
    {
        preg_match_all('/\buse\s+([A-Z][A-Za-z0-9_]*\\\\[^;]+);/', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $root = strstr($match[1], '\\', true);
            if ($root !== false && in_array($root, $allowedRoots, true)) continue;
            $key = $match[1].'@'.$file;
            $result[$key] = ['pattern' => $match[1], 'file' => $file, 'guidance' => 'Map this external runtime dependency to a public Companyware contract or a declared independent Composer dependency.'];
        }
    }

    private function collectExternalFrontendApis(string $contents, string $file, array &$result): void
    {
        preg_match_all('/\bfrom\s+[\'\"]([^\'\"]+:[^\'\"]+)[\'\"]|\b([a-z][A-Za-z0-9_]*)\.repositoryFactory\b/', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $pattern = ($match[1] ?? '') !== '' ? $match[1] : $match[0];
            $result[$pattern.'@'.$file] = ['pattern' => $pattern, 'file' => $file, 'guidance' => 'Replace this external frontend runtime API with Companyware SDK services and the versioned HTTP API.'];
        }
    }
}
