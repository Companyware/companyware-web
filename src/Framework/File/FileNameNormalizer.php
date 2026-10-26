<?php

declare(strict_types=1);

namespace App\Framework\File;

use Companyware\Core\Framework\File\FileNameNormalizerInterface;

final class FileNameNormalizer implements FileNameNormalizerInterface
{
    public function normalize(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', '', $fileName));

        return $fileName !== '' ? $fileName : 'file';
    }
}
