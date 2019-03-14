<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\GitSearch;

use AutomaticSemver\FileI;

/**
 * GitFile
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class GitFile
        implements FileI {

    private $rootPath;

    /**
     *
     * @var string
     */
    private $relativePath;

    /**
     *
     * @var string
     */
    private $revision;

    public function __construct(string $rootPath, string $path, string $revision) {
        $this->relativePath = $path;
        $this->rootPath = $rootPath;
        $this->revision = $revision;
    }

    public function getPath(): string {
        return $this->relativePath;
    }

    public function getContent(): string {
        $cmd = "git show $this->revision:$this->relativePath";
        exec("cd $this->rootPath ; $cmd", $output);
        return implode("\n", $output);
    }

    public function getFileExtension(): string {
        $arr = explode('.', $this->relativePath);
        return strtolower(array_pop($arr));
    }

}
