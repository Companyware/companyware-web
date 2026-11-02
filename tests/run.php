<?php

declare(strict_types=1);

use App\Plugin\Compatibility\VersionConstraint;
use App\Plugin\Development\PluginSkeletonGenerator;
use App\Plugin\PluginPackageException;
use App\Plugin\PluginPackageValidator;
use Companyware\Core\Test\Plugin\PackageStructureTestHelper;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$filesystem = new Filesystem();
$root = sys_get_temp_dir().'/companyware-cw008-'.bin2hex(random_bytes(6));
try {
    $generator = new PluginSkeletonGenerator();
    $files = $generator->generate('acme-example', 'acme', $root);
    $assert(in_array('plugin.json', $files, true), 'Generator result must list plugin.json.');
    $assert(is_file($root.'/src/AcmeExamplePlugin.php'), 'Generator must replace placeholders in file names.');
    PackageStructureTestHelper::assertPackage($root);
    $validator = new PluginPackageValidator(new VersionConstraint());
    $manifest = $validator->validate($root);
    $assert($manifest->name === 'acme-example', 'Generated manifest name differs.');
    $assert($manifest->version === '0.1.0', 'Generated manifest version differs.');
    file_put_contents($root.'/src/Forbidden.php', "<?php\nnamespace Plugins\\AcmeExample;\nuse App\\Internal\\Forbidden;\n");
    try {
        $validator->validate($root);
        throw new RuntimeException('Validator accepted an internal App namespace import.');
    } catch (PluginPackageException $exception) {
        $assert($exception->getMessage() === 'plugins.error.internal_api', 'Unexpected forbidden-API validation error.');
    }
    echo "CW-008 generator and validator tests passed.\n";
} finally {
    $filesystem->remove($root);
}
