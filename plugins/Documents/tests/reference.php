<?php

declare(strict_types=1);

use Companyware\Core\Framework\File\FileNameNormalizerInterface;
use Companyware\Core\Framework\Plugin\Contract\PluginConfigurationInterface;
use Companyware\Core\Framework\Plugin\Event\BeforePluginLifecycleEvent;
use Companyware\Core\Framework\Plugin\Event\PluginLifecycleOperation;
use Companyware\Core\Test\Plugin\PackageStructureTestHelper;
use Plugins\Documents\DocumentsApiOperationProvider;
use Plugins\Documents\DocumentsFileNameNormalizer;
use Plugins\Documents\DocumentsLifecycle;
use Plugins\Documents\DocumentsLifecycleGuardSubscriber;

require dirname(__DIR__, 3).'/vendor/autoload.php';
App\Plugin\PluginClassLoader::register(dirname(__DIR__, 2));

$pluginDir = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

PackageStructureTestHelper::assertPackage($pluginDir, [
    'composer.json', 'plugin.json', 'README.md', 'config/services.yaml', 'config/routes.yaml',
    'translations/messages.de.yaml', 'translations/messages.en.yaml', 'public/documents.js',
]);

$manifest = json_decode((string) file_get_contents($pluginDir.'/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$composer = json_decode((string) file_get_contents($pluginDir.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$assert($manifest['version'] === $composer['version'], 'Manifest and Composer versions differ.');
$assert(($manifest['publisher']['id'] ?? null) === 'companyware', 'Reference publisher identity differs.');

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir.'/src', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $source = (string) file_get_contents($file->getPathname());
        $assert(!str_contains($source, 'use App\\') && !str_contains($source, '\\App\\'), 'Reference plugin imports an internal App namespace.');
    }
}

$inner = new class implements FileNameNormalizerInterface {
    public function normalize(string $fileName): string { return trim($fileName); }
};
$assert((new DocumentsFileNameNormalizer($inner))->normalize('  quarterly   report.pdf  ') === 'quarterly report.pdf', 'Decorator behavior differs.');

$validConfiguration = new class implements PluginConfigurationInterface {
    public function get(string $pluginName, string $key, mixed $default = null): mixed { return 15; }
};
(new DocumentsLifecycleGuardSubscriber($validConfiguration))->validateConfiguration(new BeforePluginLifecycleEvent('documents', PluginLifecycleOperation::Activate));
$invalidConfiguration = new class implements PluginConfigurationInterface {
    public function get(string $pluginName, string $key, mixed $default = null): mixed { return 101; }
};
try {
    (new DocumentsLifecycleGuardSubscriber($invalidConfiguration))->validateConfiguration(new BeforePluginLifecycleEvent('documents', PluginLifecycleOperation::Activate));
    throw new RuntimeException('Lifecycle guard accepted an invalid upload limit.');
} catch (LogicException) {
}

$paths = (new DocumentsApiOperationProvider())->paths();
$assert(isset($paths['/documents']['get'], $paths['/documents']['post'], $paths['/documents/{id}']['delete'], $paths['/documents/{id}/content']['get']), 'Reference API operations are incomplete.');
$assert(str_contains((string) file_get_contents($pluginDir.'/public/documents.js'), 'window.CompanywareAdmin'), 'Administration SDK registration is missing.');

$temporaryProject = sys_get_temp_dir().'/companyware-documents-reference-'.bin2hex(random_bytes(5));
$directory = $temporaryProject.'/var/documents';
mkdir($directory, 0770, true);
file_put_contents($directory.'/disposable', 'test');
$lifecycle = new DocumentsLifecycle($temporaryProject);
$lifecycle->install();
$lifecycle->update('1.8.0');
$lifecycle->activate();
$lifecycle->deactivate();
$lifecycle->uninstall();
$assert(!is_dir($directory), 'Lifecycle uninstall did not remove plugin-owned files.');
@rmdir($temporaryProject.'/var');
@rmdir($temporaryProject);

echo "Documents reference plugin tests passed.\n";
