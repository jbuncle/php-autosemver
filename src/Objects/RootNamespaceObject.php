<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * newPHPClass
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class RootNamespaceObject
        extends AbstractNamespace {

    private $stmts;

    function __construct($stmts) {
        $this->stmts = $stmts;
    }

    public function getPath(): string {
        return '\\';
    }

    public function getStatements(): array {
        return $this->stmts;
    }

    public function getSignatures(): array {
        $signatures = [];

        foreach ($this->getObjects() as $object) {
            foreach ($object->getSignatures() as $signature) {
                $signatures[] = $signature;
            }
        }
        return $signatures;
    }

}
