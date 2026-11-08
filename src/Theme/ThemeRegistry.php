<?php

declare(strict_types=1);

namespace App\Theme;

use App\Infrastructure\Database;
use App\Infrastructure\PluginRegistry;
use App\Infrastructure\Schema;
use Companyware\Core\Framework\Theme\ThemeDefinition;
use Companyware\Core\Framework\Theme\ThemeRegistryInterface;
use Companyware\Core\Framework\Theme\ColorSchemeInterface;

final class ThemeRegistry implements ThemeRegistryInterface, ColorSchemeInterface
{
    public const CORE = 'core-default';

    public function __construct(private readonly PluginRegistry $plugins, private readonly Database $database, private readonly Schema $schema) {}

    public function themes(): array
    {
        $themes = [new ThemeDefinition(self::CORE, 'theme.core', 'core', 'full', ['/assets/theme/core.css'], 0)];
        $ids = [self::CORE => true];
        foreach ($this->plugins->activeManifests() as $plugin) {
            foreach ($plugin->themes as $theme) {
                if (isset($ids[$theme['id']])) throw new \LogicException(sprintf('Duplicate theme ID "%s".', $theme['id']));
                $ids[$theme['id']] = true;
                $styles = array_map(fn (string $path): string => '/plugin-assets/'.rawurlencode($plugin->name).'?path='.rawurlencode($path).'&v='.rawurlencode((string)(filemtime($plugin->path.'/public/'.$path)?:$plugin->version)), $theme['stylesheets']);
                $themes[] = new ThemeDefinition($theme['id'], $theme['label'], $plugin->name, $theme['type'], $styles, $theme['priority'] ?? 100);
            }
        }
        usort($themes, static fn (ThemeDefinition $a, ThemeDefinition $b): int => [$a->priority, $a->owner, $a->id] <=> [$b->priority, $b->owner, $b->id]);
        return $themes;
    }

    public function selected(): ThemeDefinition
    {
        $this->schema->ensure();
        $id = $this->database->connection()->query("SELECT config_value FROM system_config WHERE config_key='theme.selected'")->fetchColumn();
        foreach ($this->themes() as $theme) if ($theme->type === 'full' && $theme->id === $id) return $theme;
        foreach ($this->themes() as $theme) if ($theme->id === self::CORE) return $theme;
        throw new \LogicException('Core theme fallback is unavailable.');
    }

    public function stylesheets(): array
    {
        $selected = $this->selected();
        $styles = $selected->stylesheets;
        foreach ($this->themes() as $theme) if ($theme->type === 'partial') $styles = [...$styles, ...$theme->stylesheets];
        return array_values(array_unique($styles));
    }

    public function select(string $themeId): void
    {
        $theme = current(array_filter($this->themes(), static fn (ThemeDefinition $theme): bool => $theme->id === $themeId && $theme->type === 'full'));
        if (!$theme) throw new \InvalidArgumentException('theme.error.unavailable');
        $statement = $this->database->connection()->prepare("INSERT INTO system_config (config_key,config_value) VALUES ('theme.selected',:value) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)");
        $statement->execute(['value' => $theme->id]);
    }

    public function mode(): string
    {
        $this->schema->ensure();
        $mode=$this->database->connection()->query("SELECT config_value FROM system_config WHERE config_key='theme.color_scheme'")->fetchColumn();
        return is_string($mode)&&in_array($mode,['system','light','dark'],true)?$mode:'system';
    }

    public function selectMode(string $mode): void
    {
        if(!in_array($mode,['system','light','dark'],true))throw new \InvalidArgumentException('theme.error.color_scheme');
        $statement=$this->database->connection()->prepare("INSERT INTO system_config (config_key,config_value) VALUES ('theme.color_scheme',:value) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value)");
        $statement->execute(['value'=>$mode]);
    }
}
