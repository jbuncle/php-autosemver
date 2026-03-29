<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\GitSearch;

use RuntimeException;

/**
 * GitSearch
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class GitSearch {

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
     * @return GitFile[]
     */
    public function findFiles(string $root, string $revision): array {
        $cmd = sprintf(
            'cd %s && git ls-tree -r --name-only %s',
            escapeshellarg($root),
            escapeshellarg($revision)
        );
        $files = [];
        $resultCode = 0;
        exec($cmd, $files, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('Failed to list files from git');
        }

        $gitFiles = [];
        foreach ($files as $relPath) {
            if (call_user_func($this->filter, (string) $relPath)) {
                $gitFiles[] = new GitFile($root, $relPath, $revision);
            }
        }

        return $gitFiles;
    }

}
