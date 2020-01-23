<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\FileSearch;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ImageSearch
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SystemFileSearch {

    /**
     *
     * @var callable
     */
    private $filter;

    public function __construct(callable $filter) {
        $this->filter = $filter;
    }

    /**
     * 
     * @param string $root
     * @return SystemFile[]
     */
    public function findFiles(string $root): array {
        $it = new RecursiveDirectoryIterator($root);

        $files = [];
        foreach (new RecursiveIteratorIterator($it) as $file) {
            if (in_array(basename($file), ['.', '..'])) {
                continue;
            }
            $relPath = $this->relPath($root, (string) $file);
            if (call_user_func($this->filter, (string) $relPath)) {
                $files[] = new SystemFile($root, $relPath);
            }
        }

        return $files;
    }

    private function relPath(string $root, string $path) {
        $part = substr($path, 0, strlen($root));
        if ($part === $root) {
            $relPath = substr($path, strlen($root) + 1);
            return str_replace(DIRECTORY_SEPARATOR, '/', $relPath);
        }
        throw new Exception("Bad path");
    }

}
