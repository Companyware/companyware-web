# Theme contract

Companyware renders server-side pages with Twig. Vue is optional for interactive islands and consumes the same semantic CSS tokens.

## Stable composition rules

Use Twig inheritance for intentional structural ownership and plugin slots for additive contributions. Stable Core blocks include `head`, `title`, `theme_stylesheets`, `stylesheets`, `body_class`, `body`, `javascripts`, the administration `sidebar`, `topbar`, `content` blocks and the documented dashboard blocks. Additive `plugin_slot()` contributions can coexist; a template override is exclusive.

Plugins may declare themes in `plugin.json`:

```json
{
  "themes": [{
    "id": "acme.corporate",
    "label": "acme.theme.corporate",
    "type": "full",
    "stylesheets": ["theme/corporate.css"],
    "priority": 100
  }]
}
```

IDs are plugin-owned. `full` themes can be selected in system settings. Active `partial` themes are layered after the selected full theme in deterministic `priority`, owner and ID order. Stylesheets must remain below the plugin's `public/` directory and should set `--cw-*` semantic tokens.

Only the selected full theme may supply `templates/overrides/`. This prevents several plugins from silently competing for the same Twig template. If the selected plugin is unavailable or inactive, Companyware selects `core-default`. Core assets and recovery behavior therefore remain usable.

Theme packages should prefer tokens, blocks and slots over copying complete Core templates. Validate them with `app:plugins:validate-package`.

## Light and dark appearance

Theme identity and color scheme are separate. The persisted modes are `system`, `light` and `dark`; `system` follows the browser through `prefers-color-scheme`. Themes should provide both light and dark semantic token values with the stable selectors `html[data-color-scheme="light"]`, `html[data-color-scheme="dark"]` and `html[data-color-scheme="system"]` inside a dark-color media query. A theme does not need separate light/dark package identities.

Theme settings are optional and belong to the selected full theme, not to Companyware globally. A theme may declare `text`, `number`, `checkbox` and `select` fields using the same schema conventions as plugin settings. The administration renders **Edit theme** and its modal only when the selected theme declares at least one field. Core declares `color_scheme`; another theme may expose different controls or none at all.
