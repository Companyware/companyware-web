<?php

declare(strict_types=1);

namespace Companyware\Core\Framework\File;

/** @api since Companyware public API 1.0; decoration supported */
interface FileNameNormalizerInterface
{
    public function normalize(string $fileName): string;
}
