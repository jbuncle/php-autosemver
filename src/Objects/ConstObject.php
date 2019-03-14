<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of ConstObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ConstObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\ClassConst
     */
    private $constObj;

    public function __construct(\PhpParser\Node\Stmt\ClassConst $constObj) {
        $this->constObj = $constObj;
    }

    public function getSignatures(): array {
        $sigs = [];

        foreach ($this->constObj->consts as $const) {
            $sigs [] = "::$const->name";
        }
        return $sigs;
    }

}
