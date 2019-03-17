<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * TraitObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class TraitObject
        extends AbstractType {

    public function __construct(NamespaceObject $namespaceObj, \PhpParser\Node\Stmt\Trait_ $obj) {
        parent::__construct($namespaceObj, $obj);
    }

    protected function getPath() {
        return '{Trait ' .  parent::getPath() . '}';
    }

}
