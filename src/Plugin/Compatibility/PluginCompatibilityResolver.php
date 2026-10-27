<?php

declare(strict_types=1);

namespace App\Plugin\Compatibility;

use App\Plugin\PluginManifest;
use Companyware\Core\Framework\Api\PlatformVersion;

final class PluginCompatibilityResolver
{
    public function __construct(private readonly VersionConstraint $versions)
    {
    }

    /** @param list<PluginManifest> $installed */
    public function assertInstallable(PluginManifest $candidate, array $installed): void
    {
        $this->assertCompatible([...array_filter($installed, fn (PluginManifest $plugin) => $plugin->name !== $candidate->name), $candidate]);
    }

    /** @param list<PluginManifest> $installed @param list<PluginManifest> $active */
    public function assertActivatable(PluginManifest $candidate, array $installed, array $active): void
    {
        $activeNames = array_fill_keys(array_map(fn (PluginManifest $plugin) => $plugin->name, $active), true);
        $activeNames[$candidate->name] = true;
        $graph = array_values(array_filter($installed, fn (PluginManifest $plugin) => isset($activeNames[$plugin->name])));
        if (!array_filter($graph, fn (PluginManifest $plugin) => $plugin->name === $candidate->name)) {
            $graph[] = $candidate;
        }
        $this->assertCompatible($graph);
    }

    /** @param list<PluginManifest> $active */
    public function assertDeactivatable(PluginManifest $target, array $active): void
    {
        $this->assertCompatible(array_values(array_filter($active, fn (PluginManifest $plugin) => $plugin->name !== $target->name)));
    }

    /** @param list<PluginManifest> $installed */
    public function assertUninstallable(PluginManifest $target, array $installed): void
    {
        $this->assertCompatible(array_values(array_filter($installed, fn (PluginManifest $plugin) => $plugin->name !== $target->name)));
    }

    /** @param list<PluginManifest> $plugins */
    public function assertCompatible(array $plugins): void
    {
        $problems = $this->problems($plugins);
        if ($problems !== []) {
            throw new PluginCompatibilityException($problems);
        }
    }

    /** @param list<PluginManifest> $plugins @return list<CompatibilityProblem> */
    public function problems(array $plugins): array
    {
        $problems = [];
        $packages = [];
        foreach ($plugins as $plugin) {
            if (isset($packages[$plugin->name])) {
                $problems[] = $this->problem('duplicate', $plugin, sprintf('Plugin "%s" is present more than once.', $plugin->name));
            }
            $packages[$plugin->name] = $plugin;
        }

        $provided = $this->providedPackages($plugins);
        foreach ($plugins as $plugin) {
            $this->checkPlatform($plugin, $problems);
            foreach ($plugin->entityReferences as $owner) {
                if (!array_key_exists($owner, $plugin->requires)) {
                    $problems[] = $this->problem('entity_dependency_missing', $plugin, sprintf('Plugin "%s" references entities owned by "%s" without a required dependency.', $plugin->name, $owner), ['dependency' => $owner]);
                }
            }
            foreach ($plugin->requires as $name => $constraint) {
                $this->checkRequiredPackage($plugin, $name, $constraint, $provided, false, $problems);
            }
            foreach ($plugin->optional as $name => $constraint) {
                $this->checkRequiredPackage($plugin, $name, $constraint, $provided, true, $problems);
            }
            foreach ($plugin->conflicts as $name => $constraint) {
                foreach ($provided[$name] ?? [] as $provider) {
                    if ($provider['plugin']->name !== $plugin->name && $this->versions->matches($provider['version'], $constraint)) {
                        $problems[] = $this->problem('conflict', $plugin, sprintf('Plugin "%s" conflicts with "%s" %s provided by "%s".', $plugin->name, $name, $provider['version'], $provider['plugin']->name), ['dependency'=>$name,'constraint'=>$constraint]);
                    }
                }
            }
            foreach ($plugin->replaces as $name => $version) {
                if (isset($packages[$name]) && $packages[$name]->name !== $plugin->name) {
                    $problems[] = $this->problem('replacement_collision', $plugin, sprintf('Plugin "%s" replaces "%s" %s, but that plugin is also present.', $plugin->name, $name, $version), ['dependency'=>$name,'version'=>$version]);
                }
            }
        }

        foreach ($this->cycles($packages) as $cycle) {
            $plugin = $packages[$cycle[0]];
            $problems[] = $this->problem('cycle', $plugin, 'Circular plugin dependency: '.implode(' -> ', $cycle).'.', ['cycle'=>$cycle]);
        }

        return $problems;
    }

    private function checkPlatform(PluginManifest $plugin, array &$problems): void
    {
        if (!$this->versions->matches(PlatformVersion::VERSION, $plugin->companyware)) {
            $problems[] = $this->problem('companyware', $plugin, sprintf('Plugin "%s" requires Companyware %s; installed is %s.', $plugin->name, $plugin->companyware, PlatformVersion::VERSION), ['constraint'=>$plugin->companyware,'version'=>PlatformVersion::VERSION]);
        }
        if (!$this->versions->matches(PHP_VERSION, $plugin->php)) {
            $problems[] = $this->problem('php', $plugin, sprintf('Plugin "%s" requires PHP %s; running is %s.', $plugin->name, $plugin->php, PHP_VERSION), ['constraint'=>$plugin->php,'version'=>PHP_VERSION]);
        }
        foreach ($plugin->extensions as $extension => $constraint) {
            $version = phpversion($extension);
            if ($version === false) {
                $problems[] = $this->problem('extension_missing', $plugin, sprintf('Plugin "%s" requires missing PHP extension "%s".', $plugin->name, $extension), ['extension'=>$extension]);
            } elseif ($constraint !== '*' && !$this->versions->matches($version, $constraint)) {
                $problems[] = $this->problem('extension_version', $plugin, sprintf('Plugin "%s" requires PHP extension "%s" %s; installed is %s.', $plugin->name, $extension, $constraint, $version), ['extension'=>$extension,'constraint'=>$constraint,'version'=>$version]);
            }
        }
    }

    private function checkRequiredPackage(PluginManifest $plugin, string $name, string $constraint, array $provided, bool $optional, array &$problems): void
    {
        $providers = $provided[$name] ?? [];
        if ($providers === []) {
            if (!$optional) {
                $problems[] = $this->problem('missing', $plugin, sprintf('Plugin "%s" requires "%s" %s, but it is not available.', $plugin->name, $name, $constraint), ['dependency'=>$name,'constraint'=>$constraint]);
            }
            return;
        }
        foreach ($providers as $provider) {
            if ($this->versions->matches($provider['version'], $constraint)) {
                return;
            }
        }
        $versions = implode(', ', array_unique(array_column($providers, 'version')));
        $problems[] = $this->problem($optional ? 'optional_version' : 'version', $plugin, sprintf('Plugin "%s" requires "%s" %s; available version(s): %s.', $plugin->name, $name, $constraint, $versions), ['dependency'=>$name,'constraint'=>$constraint,'versions'=>$versions]);
    }

    private function providedPackages(array $plugins): array
    {
        $provided = [];
        foreach ($plugins as $plugin) {
            $provided[$plugin->name][] = ['plugin'=>$plugin,'version'=>$plugin->version];
            foreach ([...$plugin->provides, ...$plugin->replaces] as $name => $version) {
                $provided[$name][] = ['plugin'=>$plugin,'version'=>$version];
            }
        }
        return $provided;
    }

    private function cycles(array $packages): array
    {
        $cycles = []; $state = []; $stack = [];
        $visit = function (string $name) use (&$visit, &$cycles, &$state, &$stack, $packages): void {
            if (($state[$name] ?? 0) === 1) {
                $offset = array_search($name, $stack, true);
                $cycles[] = [...array_slice($stack, $offset), $name];
                return;
            }
            if (($state[$name] ?? 0) === 2) return;
            $state[$name] = 1; $stack[] = $name;
            foreach (array_keys($packages[$name]->requires) as $dependency) if (isset($packages[$dependency])) $visit($dependency);
            array_pop($stack); $state[$name] = 2;
        };
        foreach (array_keys($packages) as $name) $visit($name);
        return $cycles;
    }

    private function problem(string $code, PluginManifest $plugin, string $message, array $context = []): CompatibilityProblem
    {
        return new CompatibilityProblem($code, $plugin->name, $message, $context);
    }
}
