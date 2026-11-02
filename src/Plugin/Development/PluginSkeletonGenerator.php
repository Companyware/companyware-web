<?php

declare(strict_types=1);

namespace App\Plugin\Development;

use Symfony\Component\Filesystem\Filesystem;

final class PluginSkeletonGenerator
{
    /** @return list<string> */
    public function generate(string $name, string $vendor, string $target): array
    {
        if (!preg_match('/^[a-z][a-z0-9-]+$/', $name)) throw new \InvalidArgumentException('Plugin name must use lowercase letters, numbers and hyphens.');
        if (!preg_match('/^[a-z][a-z0-9_.-]+$/', $vendor)) throw new \InvalidArgumentException('Composer vendor must use lowercase letters, numbers, dots, underscores or hyphens.');
        if (file_exists($target)) throw new \RuntimeException(sprintf('Target already exists: %s', $target));

        $class = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        $namespace = 'Plugins\\'.$class;
        $route = str_replace('-', '_', $name);
        $year = gmdate('Ymd').'0001';
        $replacements = ['{{name}}' => $name, '{{vendor}}' => $vendor, '{{class}}' => $class, '{{namespace}}' => $namespace, '{{route}}' => $route, '{{migration}}' => $year];
        $files = $this->templates();
        $filesystem = new Filesystem();
        $filesystem->mkdir($target);
        try {
            foreach ($files as $relative => $contents) {
                $destination = $target.'/'.strtr($relative, $replacements);
                $filesystem->mkdir(dirname($destination));
                $filesystem->dumpFile($destination, strtr($contents, $replacements));
            }
        } catch (\Throwable $exception) {
            $filesystem->remove($target);
            throw $exception;
        }
        return array_keys($files);
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        return [
            'composer.json' => <<<'JSON'
{
  "name": "{{vendor}}/companyware-{{name}}",
  "description": "A Companyware plugin.",
  "type": "companyware-plugin",
  "version": "0.1.0",
  "license": "MIT",
  "require": {"php": "~8.3.0"},
  "autoload": {"psr-4": {"{{namespace}}\\": "src/"}},
  "extra": {"companyware-plugin": {"manifest": "plugin.json", "official": false}}
}
JSON,
            'plugin.json' => <<<'JSON'
{
  "name": "{{name}}",
  "label": "{{name}}.plugin.label",
  "description": "{{name}}.plugin.description",
  "version": "0.1.0",
  "compatibility": {"companyware": "^0.1", "php": "~8.3.0"},
  "uninstall": {"data": "remove"},
  "entities": {"references": []},
  "privileges": [{"key": "{{name}}.read", "label": "{{name}}.privilege.read", "group": "{{name}}"}],
  "migrations": ["{{namespace}}\\Migration\\Version{{migration}}"],
  "assets": {
    "styles": [{"handle": "{{name}}-css", "path": "admin.css", "priority": 100}],
    "scripts": [{"handle": "{{name}}-js", "path": "admin.js", "priority": 100, "defer": true}]
  },
  "navigation": [{"section": "nav.section.plugins", "label": "{{name}}.nav", "route": "{{route}}_index", "icon": "P", "position": 100, "privilege": "{{name}}.read"}]
}
JSON,
            'config/routes.yaml' => <<<'YAML'
{{route}}_controllers:
    resource: '../src/Controller/'
    type: attribute
    prefix: /admin/{{name}}
    defaults: { _plugin: '{{name}}' }
YAML,
            'config/services.yaml' => <<<'YAML'
services:
    _defaults:
        autowire: true
        autoconfigure: true

    {{namespace}}\:
        resource: '../src/'
        exclude: ['../src/Domain/', '../src/Migration/']

    {{namespace}}\Controller\:
        resource: '../src/Controller/'
        tags: ['controller.service_arguments']

    {{namespace}}\{{class}}Plugin:
        tags:
            - { name: 'app.plugin.lifecycle', plugin: '{{name}}' }
YAML,
            'src/{{class}}Plugin.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace {{namespace}};
use Companyware\Core\Framework\Plugin\Plugin;
final class {{class}}Plugin extends Plugin {}
PHP,
            'src/Controller/AdminController.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace {{namespace}}\Controller;
use Companyware\Core\Framework\Security\RequiresPrivilege;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
final class AdminController
{
    #[Route('', name: '{{route}}_index', methods: ['GET'])]
    #[RequiresPrivilege('{{name}}.read')]
    public function index(Environment $twig): Response
    {
        return new Response($twig->render('@{{class}}/admin/index.html.twig'));
    }
}
PHP,
            'src/Domain/Example.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace {{namespace}}\Domain;
final readonly class Example { public function __construct(public string $id) {} }
PHP,
            'src/Migration/Version{{migration}}.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace {{namespace}}\Migration;
use Companyware\Core\Framework\Plugin\Contract\Migration\PluginMigrationInterface;
use PDO;
final class Version{{migration}} implements PluginMigrationInterface
{
    public function version(): string { return '{{migration}}'; }
    public function up(PDO $connection): void {}
    public function down(PDO $connection): void {}
}
PHP,
            'templates/admin/index.html.twig' => <<<'TWIG'
{% extends 'admin/layout.html.twig' %}
{% block content %}<h1>{{ '{{name}}.title'|trans }}</h1>{% endblock %}
TWIG,
            'translations/messages.de.yaml' => "{{name}}:\n  plugin:\n    label: '{{class}}'\n    description: 'Mein Companyware-Plugin'\n  nav: '{{class}}'\n  title: '{{class}}'\n  privilege:\n    read: 'Lesen'\nsecurity:\n  group:\n    {{name}}: '{{class}}'\n",
            'public/admin.css' => ".{{name}}-root { display: block; }\n",
            'public/admin.js' => "window.AdminFramework?.emit('{{name}}:ready');\n",
            'tests/PackageTest.php' => <<<'PHP'
<?php
declare(strict_types=1);
namespace {{namespace}}\Tests;
// Add PHPUnit or Pest in require-dev and exercise public Companyware test-kit helpers here.
PHP,
            '.github/workflows/validate.yml' => <<<'YAML'
name: Validate Companyware plugin
on: [push, pull_request]
jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer validate --strict
      - run: php /path/to/companyware/bin/console app:plugins:validate-package "$GITHUB_WORKSPACE" --json
YAML,
            'README.md' => "# {{class}}\n\nGenerated Companyware plugin. Run `composer dump-autoload`, then validate with `php bin/console app:plugins:validate-package <path> --json`.\n",
        ];
    }
}
