<?php

declare(strict_types=1);

namespace Companyware\Core\Test\Plugin;

/** @api since Companyware public API 1.0 */
final class PackageStructureTestHelper
{
    /** @param list<string> $requiredPaths */
    public static function assertPackage(string $directory, array $requiredPaths = []): void
    {
        $requiredPaths = $requiredPaths ?: ['composer.json', 'plugin.json', 'translations/messages.de.yaml'];
        foreach ($requiredPaths as $path) {
            if (!file_exists(rtrim($directory, '/\\').'/'.$path)) {
                throw new \RuntimeException(sprintf('Plugin package misses required path "%s".', $path));
            }
        }
        foreach (['composer.json', 'plugin.json'] as $file) {
            try {
                $data = json_decode((string) file_get_contents(rtrim($directory, '/\\').'/'.$file), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException(sprintf('%s is not valid JSON.', $file), 0, $exception);
            }
            if (!is_array($data)) throw new \RuntimeException(sprintf('%s must contain a JSON object.', $file));
        }
    }
}
