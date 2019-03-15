<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\FileSearch;

use AutomaticSemver\FileI;

/**
 * File
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SystemFile
        implements FileI {

    private $rootPath;

    /**
     *
     * @var string
     */
    private $relativePath;

    public function __construct($rootPath, $path) {
        $this->relativePath = $path;
        $this->rootPath = $rootPath;
    }

    public function getPath(): string {
        return $this->relativePath;
    }

    public function getContent(): string {
        return \file_get_contents($this->rootPath . DIRECTORY_SEPARATOR . $this->relativePath);
    }

    public function getFileExtension(): string {
        $arr = explode('.', $this->relativePath);
        return strtolower(array_pop($arr));
    }

}
