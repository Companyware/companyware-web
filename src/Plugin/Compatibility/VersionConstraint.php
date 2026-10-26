<?php

declare(strict_types=1);

namespace App\Plugin\Compatibility;

final class VersionConstraint
{
    private const PART = '(?:0|[1-9]\d*)';
    private const VERSION = self::PART.'(?:\.'.self::PART.'){0,2}(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';
    private const SEMVER = self::PART.'\.'.self::PART.'\.'.self::PART.'(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';

    public function isValid(string $constraint): bool
    {
        try {
            $this->matches('1.2.3', $constraint);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public function matches(string $version, string $constraint): bool
    {
        if (!$this->isVersion($version)) {
            throw new \InvalidArgumentException(sprintf('Invalid semantic version "%s".', $version));
        }

        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        $anyMatch = false;
        foreach (preg_split('/\s*\|\|\s*/', $constraint) ?: [] as $alternative) {
            $tokens = preg_split('/(?:\s*,\s*|\s+)/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens === []) {
                throw new \InvalidArgumentException(sprintf('Invalid constraint "%s".', $constraint));
            }

            $matches = true;
            foreach ($tokens as $token) {
                $tokenMatches = $this->matchesToken($version, $token);
                $matches = $matches && $tokenMatches;
            }
            $anyMatch = $anyMatch || $matches;
        }

        return $anyMatch;
    }

    public function isVersion(string $version): bool
    {
        return (bool) preg_match('/^'.self::SEMVER.'$/', $version);
    }

    private function matchesToken(string $version, string $token): bool
    {
        if ($token === '*' || strtolower($token) === 'x') {
            return true;
        }
        if (preg_match('/^(\d+)\.(?:x|X|\*)$/', $token, $match)) {
            return str_starts_with($this->normalize($version), $match[1].'.');
        }
        if (preg_match('/^(\d+)\.(\d+)\.(?:x|X|\*)$/', $token, $match)) {
            return str_starts_with($this->normalize($version), $match[1].'.'.$match[2].'.');
        }
        if (preg_match('/^([~^])('.self::VERSION.')$/', $token, $match)) {
            $lower = $this->normalize($match[2]);
            [$major, $minor, $patch] = array_map('intval', explode('.', $lower));
            $segments = substr_count(preg_replace('/[-+].*$/', '', $match[2]), '.') + 1;
            if ($match[1] === '^') {
                $upper = $major > 0 || $segments === 1
                    ? ($major + 1).'.0.0'
                    : ($minor > 0 || $segments === 2 ? '0.'.($minor + 1).'.0' : '0.0.'.($patch + 1));
            } else {
                $upper = $segments >= 3 ? $major.'.'.($minor + 1).'.0' : ($major + 1).'.0.0';
            }
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }
        if (preg_match('/^(>=|<=|>|<|!=|==|=)?('.self::VERSION.')$/', $token, $match)) {
            return version_compare($version, $this->normalize($match[2]), ($match[1] ?? '') ?: '==');
        }

        throw new \InvalidArgumentException(sprintf('Invalid constraint token "%s".', $token));
    }

    private function normalize(string $version): string
    {
        $suffix = '';
        if (preg_match('/^([^+-]+)(.*)$/', $version, $match)) {
            $version = $match[1];
            $suffix = $match[2];
        }
        $parts = explode('.', $version);
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', $parts).$suffix;
    }
}
