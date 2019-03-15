<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of FunctionObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class FunctionObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Function_ 
     */
    private $functionObj;

    public function __construct(\PhpParser\Node\Stmt\Function_ $functionObj) {
        $this->functionObj = $functionObj;
    }

    public function getSignatures(): array {
        return [$this->functionObj->name];
    }

}
