<?php
/*
 * Companyware Web release-note policy
 * Copyright (c) 2026 Companyware contributors
 * SPDX-License-Identifier: MIT
 * See LICENSE for license information.
 */

declare(strict_types=1);

function companywareReleaseNotesHaveVersionHeading(string $releaseNotes, string $version): bool
{
    if (str_starts_with($releaseNotes, "\xEF\xBB\xBF")) {
        $releaseNotes = substr($releaseNotes, 3);
    }

    $firstLine = preg_split('/\R/', $releaseNotes, 2)[0] ?? '';

    return $firstLine === sprintf('# Companyware Web %s', $version);
}

