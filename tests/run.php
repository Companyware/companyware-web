<?php

declare(strict_types=1);

use App\Plugin\Compatibility\VersionConstraint;
use App\Plugin\Development\PluginSkeletonGenerator;
use App\Plugin\Development\PluginPortabilityAnalyzer;
use App\Plugin\PluginPackageException;
use App\Plugin\PluginPackageValidator;
use App\Plugin\PluginClassLoader;
use App\Plugin\AdminAccessSubscriber;
use App\Api\OpenApiRegistry;
use App\Extension\PublisherTrustStore;
use App\Extension\PackageSignatureVerifier;
use App\Extension\ExtensionRegistryClient;
use Companyware\Core\Test\Plugin\PackageStructureTestHelper;
use Companyware\Core\Framework\Security\DataScope;
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
    $assert($manifest->publisherId === 'acme' && $manifest->publisherName === 'Acme', 'Generated publisher metadata differs.');
    file_put_contents($root.'/public/theme.css', ':root{--cw-color-primary:#123456}');
    $themeManifest=json_decode((string)file_get_contents($root.'/plugin.json'),true,512,JSON_THROW_ON_ERROR);$themeManifest['themes']=[['id'=>'acme-example.default','label'=>'acme-example.theme.default','type'=>'full','stylesheets'=>['theme.css'],'priority'=>100,'settings'=>[['key'=>'density','type'=>'select','label'=>'acme-example.theme.density','default'=>'comfortable','options'=>[['value'=>'comfortable','label'=>'acme-example.theme.comfortable'],['value'=>'compact','label'=>'acme-example.theme.compact']]]]]];file_put_contents($root.'/plugin.json',json_encode($themeManifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $validatedTheme=$validator->validate($root)->themes[0];$assert($validatedTheme['id']==='acme-example.default'&&$validatedTheme['settings'][0]['key']==='density','Theme manifest validation differs.');
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

    $assert(is_file(dirname(__DIR__).'/admin-sdk/dist/companyware-admin-sdk.js'), 'Administration SDK browser build is missing.');
    $assert(is_file(dirname(__DIR__).'/admin-sdk/dist/companyware-admin-tokens.css'), 'Administration SDK semantic tokens are missing.');
    $assert(is_file($root.'/resources/administration/src/pages/index/App.vue'), 'Generator must provide the native Vue administration starter.');
    $assert(is_file($root.'/resources/administration/vite.config.ts'), 'Generator must provide the Vite build configuration.');
    $assert(str_contains((string) file_get_contents($root.'/README.md'), 'FrontendProviderInterface'), 'Generated documentation must explain optional public frontends.');
    file_put_contents($root.'/src/ExternalPortExample.php', "<?php\nuse ExternalCommerce\\Framework\\Data\\Entity;\n");
    file_put_contents($root.'/public/external-admin.js', "ExternalAdmin.Module.register('acme-example', {});\n");
    $portability = (new PluginPortabilityAnalyzer())->analyze($root);
    $assert($portability['summary']['transformable'] === 1, 'External administration registration must be recognized as transformable.');
    $assert(count(array_filter($portability['manualMappings'], static fn (array $finding): bool => str_starts_with($finding['pattern'], 'ExternalCommerce\\'))) === 1, 'External PHP runtime use must be reported for manual mapping.');
    echo "CW-010 administration SDK and Vue generator tests passed.\n";
    $assert(AdminAccessSubscriber::isProtectedAdminPath('/admin'), 'Administration root must remain protected.');
    $assert(AdminAccessSubscriber::isProtectedAdminPath('/admin/plugins'), 'Administration child routes must remain protected.');
    $assert(!AdminAccessSubscriber::isProtectedAdminPath('/admin-sdk/companyware-admin-sdk.js'), 'Public SDK assets must not be captured by the administration guard.');
    echo "Administration route boundary tests passed.\n";
    $trust=new PublisherTrustStore($root);if(PackageSignatureVerifier::available()){$keyPair=sodium_crypto_sign_keypair();$public=base64_encode(sodium_crypto_sign_publickey($keyPair));$secret=sodium_crypto_sign_secretkey($keyPair);$trust->trust('acme-publisher','release-1',$public);$archive=$root.'/signed-package.zip';file_put_contents($archive,'signed extension payload');$hash=hash_file('sha256',$archive);$release=['name'=>'acme-example','version'=>'0.2.0','sha256'=>$hash,'manifest'=>['publisher'=>['id'=>'acme-publisher','name'=>'Acme Publisher']],'signature'=>['publisher'=>'acme-publisher','keyId'=>'release-1','value'=>base64_encode(sodium_crypto_sign_detached(PackageSignatureVerifier::payload('acme-example','0.2.0',$hash),$secret))]];(new PackageSignatureVerifier($trust))->verify($archive,$release);$mismatch=$release;$mismatch['manifest']['publisher']['id']='other-publisher';try{(new PackageSignatureVerifier($trust))->verify($archive,$mismatch);throw new RuntimeException('Verifier accepted a mismatching publisher.');}catch(RuntimeException $exception){$assert($exception->getMessage()==='Package publisher does not match the signed release manifest.','Unexpected publisher mismatch diagnostic.');}file_put_contents($archive,'tampered payload');try{(new PackageSignatureVerifier($trust))->verify($archive,$release);throw new RuntimeException('Verifier accepted a tampered package.');}catch(RuntimeException $exception){$assert($exception->getMessage()==='Package checksum verification failed.','Unexpected tamper diagnostic.');}$trust->revoke('acme-publisher','release-1');}
    $registryFile=$root.'/registry.json';file_put_contents($registryFile,json_encode(['format'=>'companyware-extension-registry-1','packages'=>[],'advisories'=>[]],JSON_THROW_ON_ERROR));$assert((new ExtensionRegistryClient())->registry($registryFile)['format']==='companyware-extension-registry-1','Registry protocol must accept its versioned local fixture.');
    echo "CW-011 registry trust and signature tests passed.\n";
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/plugins/Documents/tests/reference.php');passthru($command,$documentsExit);$assert($documentsExit===0,'Documents reference plugin tests failed.');
    echo "CW-012 reference package tests passed.\n";
    $assert(DataScope::Own->rank()<DataScope::Department->rank()&&DataScope::Department->rank()<DataScope::All->rank(),'Scoped permission precedence differs.');
    echo "CW-007A scoped permission contract tests passed.\n";
    $assert(is_file(dirname(__DIR__).'/public/assets/theme/core.css'),'Protected Core theme fallback is missing.');
    $assert(str_contains((string)file_get_contents(dirname(__DIR__).'/templates/base.html.twig'),'theme_stylesheets'),'Base template does not expose the stable theme block.');
    $themeCss=(string)file_get_contents(dirname(__DIR__).'/public/assets/theme/core.css');
    $assert(str_contains($themeCss,'prefers-color-scheme: dark')&&str_contains($themeCss,'data-color-scheme="dark"'),'Core theme does not provide system and forced dark modes.');
    $settingsTemplate=(string)file_get_contents(dirname(__DIR__).'/templates/admin/settings/index.html.twig');
    $assert(str_contains($settingsTemplate,'currentTheme.settings|length')&&str_contains($settingsTemplate,'theme-settings-dialog'),'Theme settings modal is not conditional on declared settings.');
    echo "CW-013 theme contract tests passed.\n";
} finally {
    $filesystem->remove($root);
}
