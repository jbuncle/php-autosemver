<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\FileSearch;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Description of ImageSearch
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SystemFileSearch {

    /**
     * 
     * @param string $root
     * @return SystemFile[]
     */
    public function findFiles(string $root, array $paths): array {
        $it = new RecursiveDirectoryIterator($root);

        $files = [];
        foreach (new RecursiveIteratorIterator($it) as $file) {
            $relPath = $this->relPath($root, (string) $file);
            if (self::startsWithAny($relPath, $paths)) {
                $files [] = new SystemFile($root, $relPath);
            }
        }

        return $files;
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

    private function relPath(string $root, string $path) {
        $part = substr($path, 0, strlen($root));
        if ($part === $root) {
            return substr($path, strlen($root) + 1);
        }
        throw new Exception("Bad path");
    }

}
