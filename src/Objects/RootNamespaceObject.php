<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;

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

    protected function prefixSignatureModel(LegacySignature $signature): LegacySignature {
        return $signature;
    }

}
