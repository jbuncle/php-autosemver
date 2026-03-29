<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk)  - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\FileSearch\SystemFileSearch;
use AutomaticSemver\GitSearch\GitSearch;
use AutomaticSemver\Signature\IdentityKey;
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

        $unchanged = [];
        $new = [];
        foreach ($current as $bucket) {
            if ($this->findMatchingBucket($bucket->getIdentity(), $previous) !== null) {
                $unchanged[] = $bucket;
            } else {
                $new[] = $bucket;
            }
        }

        $removed = [];
        foreach ($previous as $bucket) {
            if ($this->findMatchingBucket($bucket->getIdentity(), $current) === null) {
                $removed[] = $bucket;
            }
        }

        return new DiffReport(
            $startRevision,
            $endRevision,
            new DiffEntries($unchanged, $new, $removed)
        );
    }

    /**
     * @param LegacySignature[] $signatures
     * @return SignatureBucket[]
     */
    private function indexSignatures(array $signatures): array {
        $index = [];
        foreach ($signatures as $signature) {
            $matchedIndex = $this->findMatchingBucketIndex($signature, $index);
            if ($matchedIndex === null) {
                $index[] = new SignatureBucket($signature, [$signature->toLegacyString()]);
                continue;
            }

            $index[$matchedIndex]->addDisplay($signature->toLegacyString());
        }
        return $index;
    }

    /**
     * @param SignatureBucket[] $entries
     */
    private function findMatchingBucket(IdentityKey $identity, array $entries): ?SignatureBucket {
        $matchedIndex = $this->findMatchingBucketIndex($identity, $entries);
        if ($matchedIndex === null) {
            return null;
        }

        return $entries[$matchedIndex];
    }

    /**
     * @param SignatureBucket[] $entries
     */
    private function findMatchingBucketIndex(IdentityKey $identity, array $entries): ?int {
        foreach ($entries as $index => $entry) {
            if ($this->identitiesMatch($entry->getIdentity(), $identity)) {
                return $index;
            }
        }

        return null;
    }

    private function identitiesMatch(IdentityKey $left, IdentityKey $right): bool {
        return $left->equals($right) || $right->equals($left);
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
