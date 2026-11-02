<?php

declare(strict_types=1);

use App\Plugin\Compatibility\VersionConstraint;
use App\Plugin\Development\PluginSkeletonGenerator;
use App\Plugin\PluginPackageException;
use App\Plugin\PluginPackageValidator;
use App\Plugin\PluginClassLoader;
use App\Api\OpenApiRegistry;
use Companyware\Core\Test\Plugin\PackageStructureTestHelper;
use Plugins\Documents\DocumentsApiOperationProvider;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';
PluginClassLoader::register(dirname(__DIR__).'/plugins');

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

    $openApi=(new OpenApiRegistry([new DocumentsApiOperationProvider()]))->document();
    $assert(isset($openApi['paths']['/oauth/token']['post']),'OpenAPI must expose OAuth2 client credentials.');
    $assert(isset($openApi['paths']['/documents']['get'],$openApi['paths']['/documents']['post']),'Plugin API provider must expose list and create operations.');
    $assert(isset($openApi['paths']['/documents/{id}']['delete'],$openApi['paths']['/documents/{id}/content']['get']),'OpenAPI must include plugin delete and content operations.');
    $assert(isset($openApi['components']['securitySchemes']['oauth2']),'OpenAPI must publish the OAuth2 security scheme.');
    echo "CW-009 plugin OpenAPI registry tests passed.\n";
} finally {
    $filesystem->remove($root);
}
