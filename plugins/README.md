# Plugins

Each plugin lives in its own directory and exposes a `plugin.json` manifest:

```json
{
  "name": "vendor-plugin-name",
  "label": "Example plugin",
  "description": "Adds an administration capability.",
  "version": "1.0.0"
}
```

The core registry discovers manifests automatically. Application services can be replaced through Symfony's service container; later plugin contracts can add navigation, routes, configuration and lifecycle hooks without coupling those capabilities to the core UI.
