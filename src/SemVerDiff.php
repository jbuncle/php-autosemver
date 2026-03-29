<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk)  - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\FileSearch\SystemFileSearch;
use AutomaticSemver\GitSearch\GitSearch;
use AutomaticSemver\Signature\LegacySignature;

/**
 * SemVerDiff
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SemVerDiff {

    private $root;
    private $includePaths;
    private $excludePaths;

    public function __construct(string $root, array $includePaths, array $excludePaths) {
        $this->root = $root;
        $this->includePaths = $includePaths;
        $this->excludePaths = $excludePaths;
    }

    private function loadGitIgnore(string $file): array {
        if (!file_exists($file)) {
            return [];
        }

        $fileContents = file_get_contents($file);
        if ($fileContents === false) {
            return [];
        }

        $lines = explode("\n", $fileContents);

        $clean = array_map(function(string $line): string {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                return '';
            }

            $line = preg_replace('/\s+#.*$/', '', $line);
            return trim((string) $line);
        }, $lines);

        return array_values(array_filter($clean, function(string $line): bool {
            return !empty($line);
        }));
    }

    private function isGitIgnored(string $filePath, array $ignores): bool {
        $filePath = '/' . $filePath;
        foreach ($ignores as $ignorePattern) {
            $pattern = $ignorePattern;
            if (strpos($pattern, '/') === 0) {
                $pattern = $ignorePattern . '*';
            } else {
                $pattern = '*' . $ignorePattern . '*';
            }

            if (fnmatch($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }

    public function diff(string $startRevision, string $endRevision): DiffReport {

        $gitIgnores = $this->loadGitIgnore($this->root . DIRECTORY_SEPARATOR . '.gitignore');
        $gitFilter = function(string $relPath) use ($gitIgnores): bool {
            return $this->isGitIgnored($relPath, $gitIgnores);
        };
        $filter = function(string $relPath) use ($gitFilter): bool {
            if (!self::endsWith($relPath, '.php')) {
                return false;
            }
            if (!empty($this->includePaths) && !self::startsWithAny($relPath, $this->includePaths)) {
                return false;
            }
            if (!empty($this->excludePaths) && self::startsWithAny($relPath, $this->excludePaths)) {
                return false;
            }
            return !$gitFilter($relPath);
        };
        $signatureSearch = new SignatureSearch();

        $startFiles = $this->getFilesForLabel($startRevision, $filter);
        $endFiles = $this->getFilesForLabel($endRevision, $filter);

        $previous = $this->indexSignatures($signatureSearch->getSignatureModels($startFiles));
        $current = $this->indexSignatures($signatureSearch->getSignatureModels($endFiles));

        $unchangedKeys = array_intersect(array_keys($current), array_keys($previous));
        $newKeys = array_diff(array_keys($current), array_keys($previous));
        $removedKeys = array_diff(array_keys($previous), array_keys($current));

        return new DiffReport(
                $startRevision,
                $endRevision,
                $this->mapSignatureKeysToStrings($unchangedKeys, $current),
                $this->mapSignatureKeysToStrings($newKeys, $current),
                $this->mapSignatureKeysToStrings($removedKeys, $previous)
        );
    }

    /**
     * @param LegacySignature[] $signatures
     * @return array<string, string>
     */
    private function indexSignatures(array $signatures): array {
        $index = [];
        foreach ($signatures as $signature) {
            $index[$signature->toIdentityKey()] = $signature->toLegacyString();
        }
        return $index;
    }

    /**
     * @param string[] $keys
     * @param array<string, string> $index
     * @return string[]
     */
    private function mapSignatureKeysToStrings(array $keys, array $index): array {
        return array_values(array_map(function (string $key) use ($index): string {
            return $index[$key];
        }, $keys));
    }

    private static function startsWithAny(string $str, array $prefixes) {
        foreach ($prefixes as $prefix) {
            if (self::startsWith($str, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private static function startsWith(string $str, string $prefix) {
        return $prefix === substr($str, 0, strlen($prefix));
    }

    /**
     *
     * @param string $label
     * @param callable $filter
     *
     * @return array<FileI>
     */
    private function getFilesForLabel(string $label, callable $filter): array {
        if (strcasecmp($label, 'wc') === 0) {
            $fileSearch = new SystemFileSearch($filter);
            return $fileSearch->findFiles($this->root);
        } else {
            $gitSearch = new GitSearch($filter);
            return $gitSearch->findFiles($this->root, $label);
        }
    }

    private static function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

}
