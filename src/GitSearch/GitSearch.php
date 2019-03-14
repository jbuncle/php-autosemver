<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\GitSearch;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * GitSearch
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class GitSearch {

    /**
     * 
     * @param string $root
     * @return GitFile[]
     */
    public function findFiles(string $root, array $paths, string $revision): array {
        $cmd = "git ls-tree -r --name-only '$revision'";
        exec("cd $root ; $cmd", $files);

        $gitFiles = [];
        foreach ($files as $relPath) {
            if (self::startsWithAny($relPath, $paths)) {
                $gitFiles[] = new GitFile($root, $relPath, $revision);
            }
        }

        return $gitFiles;
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

}
