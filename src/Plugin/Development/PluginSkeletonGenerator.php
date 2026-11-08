<?php
/*
 * Companyware Web plugin skeleton generator
 * Copyright (c) 2026 Companyware contributors
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */

declare(strict_types=1);

namespace App\Plugin\Development;

use Symfony\Component\Filesystem\Filesystem;

final class PluginSkeletonGenerator
{
    /** @return list<string> */
    public function generate(string $name, string $vendor, string $target, ?string $publisherId = null, ?string $publisherName = null): array
    {
        if (!preg_match('/^[a-z][a-z0-9-]+$/', $name)) throw new \InvalidArgumentException('Plugin name must use lowercase letters, numbers and hyphens.');
        if (!preg_match('/^[a-z][a-z0-9_.-]+$/', $vendor)) throw new \InvalidArgumentException('Composer vendor must use lowercase letters, numbers, dots, underscores or hyphens.');
        $publisherId ??= $vendor;
        $publisherName ??= ucwords(str_replace(['-', '_', '.'], ' ', $publisherId));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $publisherId)) throw new \InvalidArgumentException('Publisher ID must use lowercase letters, numbers, dots, underscores or hyphens.');
        if (trim($publisherName) === '' || mb_strlen($publisherName) > 190) throw new \InvalidArgumentException('Publisher name must contain between 1 and 190 characters.');
        $publisherNameText = str_replace('*/', '* /', preg_replace('/[\r\n]+/', ' ', trim($publisherName)) ?? trim($publisherName));
        if (file_exists($target)) throw new \RuntimeException(sprintf('Target already exists: %s', $target));

        $class = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        $namespace = 'Plugins\\'.$class;
        $route = str_replace('-', '_', $name);
        $year = gmdate('Ymd').'0001';
        $copyrightYear = gmdate('Y');
        $publisherNameJson = substr((string) json_encode($publisherName, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 1, -1);
        $replacements = ['{{name}}' => $name, '{{vendor}}' => $vendor, '{{publisher_id}}' => $publisherId, '{{publisher_name}}' => $publisherNameJson, '{{publisher_name_text}}' => $publisherNameText, '{{class}}' => $class, '{{namespace}}' => $namespace, '{{namespace_json}}' => str_replace('\\', '\\\\', $namespace), '{{route}}' => $route, '{{migration}}' => $year, '{{copyright_year}}' => $copyrightYear];
        $files = $this->templates();
        $generated = [];
        $filesystem = new Filesystem();
        $filesystem->mkdir($target);
        try {
            foreach ($files as $relative => $contents) {
                $relative = strtr($relative, $replacements);
                $destination = $target.'/'.$relative;
                $filesystem->mkdir(dirname($destination));
                $filesystem->dumpFile($destination, strtr($contents, $replacements));
                $generated[] = $relative;
            }
        } catch (\Throwable $exception) {
            $filesystem->remove($target);
            throw $exception;
        }
        return $generated;
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        return [
            'LICENSE' => <<<'TEXT'
MIT License

Copyright (c) {{copyright_year}} {{publisher_name_text}}

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
TEXT,
            'composer.json' => <<<'JSON'
{
  "name": "{{vendor}}/companyware-{{name}}",
  "description": "A Companyware plugin.",
  "type": "companyware-plugin",
  "version": "0.1.0",
  "license": "MIT",
  "require": {"php": "~8.3.0"},
  "autoload": {"psr-4": {"{{namespace_json}}\\": "src/"}},
  "scripts": {"test": "@php tests/smoke.php"},
  "extra": {"companyware-plugin": {"manifest": "plugin.json", "official": false}}
}
JSON,
            'plugin.json' => <<<'JSON'
{
  "name": "{{name}}",
  "label": "{{name}}.plugin.label",
  "description": "{{name}}.plugin.description",
  "version": "0.1.0",
  "publisher": {"id": "{{publisher_id}}", "name": "{{publisher_name}}"},
  "compatibility": {"companyware": "^0.1", "php": "~8.3.0"},
  "uninstall": {"data": "remove"},
  "entities": {"references": []},
  "privileges": [{"key": "{{name}}.read", "label": "{{name}}.privilege.read", "group": "{{name}}"}],
  "migrations": ["{{namespace_json}}\\Migration\\Version{{migration}}"],
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

    '{{namespace}}\':
        resource: '../src/'
        exclude: ['../src/Domain/', '../src/Migration/']

    '{{namespace}}\Controller\':
        resource: '../src/Controller/'
        tags: ['controller.service_arguments']

    '{{namespace}}\{{class}}Plugin':
        tags:
            - { name: 'app.plugin.lifecycle', plugin: '{{name}}' }
YAML,
            'src/{{class}}Plugin.php' => <<<'PHP'
<?php
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
declare(strict_types=1);
namespace {{namespace}};
use Companyware\Core\Framework\Plugin\Plugin;
final class {{class}}Plugin extends Plugin {}
PHP,
            'src/Controller/AdminController.php' => <<<'PHP'
<?php
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
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
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
declare(strict_types=1);
namespace {{namespace}}\Domain;
final readonly class Example { public function __construct(public string $id) {} }
PHP,
            'src/Migration/Version{{migration}}.php' => <<<'PHP'
<?php
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
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
{#
  {{name}} - Companyware plugin
  Copyright (c) {{copyright_year}} {{publisher_name_text}}
  SPDX-License-Identifier: MIT
  See LICENSE for license information.
#}
{% extends 'admin/layout.html.twig' %}
{% block content %}<h1>{{ '{{name}}.title'|trans }}</h1>{% endblock %}
TWIG,
            'translations/messages.de.yaml' => "{{name}}:\n  plugin:\n    label: '{{class}}'\n    description: 'Mein Companyware-Plugin'\n  nav: '{{class}}'\n  title: '{{class}}'\n  privilege:\n    read: 'Lesen'\nsecurity:\n  group:\n    {{name}}: '{{class}}'\n",
            'public/admin.css' => "/*\n * {{name}} - Companyware plugin\n * Copyright (c) {{copyright_year}} {{publisher_name_text}}\n * SPDX-License-Identifier: MIT\n * See LICENSE for license information.\n */\n.{{name}}-root { display: block; }\n",
            'public/admin.js' => "/*\n * {{name}} - Companyware plugin\n * Copyright (c) {{copyright_year}} {{publisher_name_text}}\n * SPDX-License-Identifier: MIT\n * See LICENSE for license information.\n */\nwindow.CompanywareAdmin?.hooks.emit('{{name}}:ready');\n",
            'resources/administration/package.json' => <<<'JSON'
{
  "name": "@{{vendor}}/{{name}}-admin",
  "private": true,
  "version": "0.1.0",
  "type": "module",
  "scripts": {"dev": "vite", "build": "vue-tsc --noEmit && vite build"},
  "dependencies": {"vue": "^3.5.0"},
  "devDependencies": {"@vitejs/plugin-vue": "^6.0.0", "typescript": "^5.9.0", "vite": "^7.0.0", "vue-tsc": "^3.0.0"}
}
JSON,
            'resources/administration/tsconfig.json' => <<<'JSON'
{"compilerOptions":{"target":"ES2022","module":"ESNext","moduleResolution":"Bundler","strict":true,"lib":["ES2022","DOM"],"types":[]},"include":["src/**/*.ts","src/**/*.vue"]}
JSON,
            'resources/administration/vite.config.ts' => <<<'TS'
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import {resolve} from 'node:path';
export default defineConfig({plugins:[vue()],build:{emptyOutDir:false,outDir:'../../public',lib:{entry:resolve(__dirname,'src/main.ts'),formats:['iife'],name:'{{class}}Admin',fileName:()=> 'admin.js'}}});
TS,
            'resources/administration/src/main.ts' => <<<'TS'
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
import {createApp} from 'vue';
import App from './pages/index/App.vue';

const sdk = window.CompanywareAdmin;
sdk.modules.register({
    id: '{{name}}.admin', owner: '{{name}}', version: '1.0.0', replaceable: true,
    mount(target: Element, props: Record<string, unknown> = {}) {
        const app = createApp(App, props);
        app.provide('companyware.admin', sdk);
        return app.mount(target);
    }
});
sdk.hooks.emit('{{name}}:ready', {module: sdk.modules.get('{{name}}.admin')});
TS,
            'resources/administration/src/pages/index/App.vue' => <<<'VUE'
<!--
  {{name}} - Companyware plugin
  Copyright (c) {{copyright_year}} {{publisher_name_text}}
  SPDX-License-Identifier: MIT
  See LICENSE for license information.
-->
<script setup lang="ts">
defineProps<{title?: string}>();
</script>
<template><section class="{{name}}-root"><h1>{{ title ?? '{{class}}' }}</h1></section></template>
VUE,
            'resources/administration/src/companyware.d.ts' => <<<'TS'
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
type CompanywareModule = {id:string;owner:string;version:string;replaceable?:boolean;mount?:(target:Element,props?:Record<string,unknown>)=>unknown};
interface Window { CompanywareAdmin: {version:string;modules:{register<T extends CompanywareModule>(module:T):T;get(id:string):CompanywareModule|undefined};hooks:{on(name:string,listener:(detail:unknown)=>void):()=>void;emit(name:string,detail?:unknown):void}} }
TS,
            'tests/smoke.php' => <<<'PHP'
<?php
/*
 * {{name}} - Companyware plugin
 * Copyright (c) {{copyright_year}} {{publisher_name_text}}
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */
declare(strict_types=1);
$required=['composer.json','plugin.json','config/services.yaml','config/routes.yaml','translations/messages.de.yaml'];
foreach($required as $path)if(!file_exists(dirname(__DIR__).'/'.$path)){fwrite(STDERR,"Missing $path\n");exit(1);}
foreach(['composer.json','plugin.json'] as $file){json_decode((string)file_get_contents(dirname(__DIR__).'/'.$file),true,512,JSON_THROW_ON_ERROR);}
echo "Plugin package smoke test passed.\n";
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
      - run: composer test
      - name: Run the shared Companyware validator
        env:
          COMPANYWARE_ROOT: ${{ vars.COMPANYWARE_ROOT }}
        run: |
          test -n "$COMPANYWARE_ROOT"
          php "$COMPANYWARE_ROOT/bin/console" app:plugins:validate-package "$GITHUB_WORKSPACE" --json --no-interaction
YAML,
            'README.md' => "# {{class}}\n\nGenerated Companyware plugin. The backend and Twig page work without a JavaScript build. For an interactive Vue 3 + TypeScript administration module, run `npm install` and `npm run build` in `resources/administration/`; Vite writes `public/admin.js`. Native administration code uses only `window.CompanywareAdmin`. Analyze external plugin source before porting with `app:plugins:analyze-portability <path> --json`; the report identifies transformable registrations and dependencies needing explicit adapters. Run `php tests/smoke.php`, then validate with `php /path/to/companyware/bin/console app:plugins:validate-package . --json --no-interaction`. Public frontend pages are optional: implement `FrontendProviderInterface` for `/`, or add guarded public routes with `_plugin: '{{name}}'`. Without an active homepage provider Companyware redirects `/` to `/admin`.\n",
        ];
    }
}
