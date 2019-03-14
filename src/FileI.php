<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

/**
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
interface FileI {

    public function getPath(): string;

    public function getContent(): string;

    public function getFileExtension(): string;
}
