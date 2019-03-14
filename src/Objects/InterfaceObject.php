<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of ClassObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class InterfaceObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Interface_ 
     */
    private $classObj;

    public function __construct(\PhpParser\Node\Stmt\Interface_ $classObj) {
        $this->classObj = $classObj;
    }

    private function getPath() {
        $sig = '';
        $sig .= (string) $this->classObj->name;
        return $sig;
    }

    public function getSignatures(): array {
        $signatures = [];
        $collection = new Collection($this->classObj->stmts);
        foreach ($collection->getSignatures() as $signature) {
            $signatures[] = '{' . $this->getPath() . '}' . $signature;
        }

        return $signatures;
    }

}
