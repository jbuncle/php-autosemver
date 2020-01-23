<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk)  - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\FileSearch\SystemFileSearch;
use AutomaticSemver\GitSearch\GitSearch;

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
        $lines = explode("\n", $fileContents);


        $clean = array_map(function(string $line): string {
            $line = substr($line, strpos($line, '#'));
            return trim($line);
        }, $lines);

        return array_filter($clean, function(string $line): bool {
            return !empty($line);
        });
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

        // Failed to match
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
            if (!empty($this->includePaths) && self::startsWithAny($relPath, $this->excludePaths)) {
                return false;
            }
            return !$gitFilter($relPath);
        };
        $signatureSearch = new SignatureSearch();

        $startFiles = $this->getFilesForLabel($startRevision, $filter);
        $endFiles = $this->getFilesForLabel($endRevision, $filter);

        $prevSignatures = $signatureSearch->getSignatures($startFiles);
        $currentSignatures = $signatureSearch->getSignatures($endFiles);

        $unchangedSignatures = array_intersect($currentSignatures, $prevSignatures);
        $newSignatures = array_diff($currentSignatures, $prevSignatures);
        $removedSignatures = array_diff($prevSignatures, $currentSignatures);

        return new DiffReport(
                $startRevision,
                $endRevision,
                $unchangedSignatures,
                $newSignatures,
                $removedSignatures
        );
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
