<?php

declare(strict_types=1);

namespace Plugins\Documents;

use Companyware\Core\Framework\File\FileNameNormalizerInterface;

final class DocumentsFileNameNormalizer implements FileNameNormalizerInterface
{
    public function __construct(private readonly FileNameNormalizerInterface $inner)
    {
    }

    public function normalize(string $fileName): string
    {
        $fileName = $this->inner->normalize($fileName);

        return trim((string) preg_replace('/\s+/u', ' ', $fileName));
    }
}
