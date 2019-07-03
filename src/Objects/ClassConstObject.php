<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * ClassConstObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassConstObject implements Signatures {

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
            $sigs [] = "::$const->name = {$this->getValueString($const->value)}";
        }
        return $sigs;
    }

    private function getValueString($value): string {
        if ($value instanceof \PhpParser\Node\Scalar\String_) {
            return "'" . $value->value . "'";
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryMinus) {
            return "-" . $value->expr->value;
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryPlus) {
            return "+" . $value->expr->value;
        }
        return (string) $value->value;
    }

}
