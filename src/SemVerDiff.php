<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk)  - All Rights Reserved
 */

namespace AutomaticSemver;

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

    public function diff(string $startRevision, string $endRevision, bool $verbose): string {
        $filter = function(string $relPath): bool {
            if (!self::endsWith($relPath, '.php')) {
                return false;
            }
            if (!empty($this->includePaths) && !self::startsWithAny($relPath, $this->includePaths)) {
                return false;
            }
            if (!empty($this->includePaths) && self::startsWithAny($relPath, $this->excludePaths)) {
                return false;
            }

            return true;
        };
        $signatureSearch = new SignatureSearch($filter);
        $gitSearch = new GitSearch($filter);

        $startFiles = $gitSearch->findFiles($this->root, $startRevision);
        $prevSignatures = $signatureSearch->getSignatures($startFiles);

        $endFiles = $gitSearch->findFiles($this->root, $endRevision);
        $currentSignatures = $signatureSearch->getSignatures($endFiles);

        $unchangedSignatures = array_intersect($currentSignatures, $prevSignatures);
        $newSignatures = array_diff($currentSignatures, $prevSignatures);
        $removedSignatures = array_diff($prevSignatures, $currentSignatures);

        if ($verbose) {

            echo "Unchanged:\n";
            foreach ($unchangedSignatures as $unchangedSignature) {
                echo "\t$unchangedSignature\n";
            }

            echo "New:\n";
            foreach ($newSignatures as $newSignature) {
                echo "\t$newSignature\n";
            }

            echo "Removed:\n";
            foreach ($removedSignatures as $removedSignature) {
                echo "\t$removedSignature\n";
            }
        }

        if (!empty($removedSignatures)) {
            return "MAJOR";
        } else if (!empty($newSignatures)) {
            return "MINOR";
        } else {
            return "PATCH";
        }
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

    private static function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

}
