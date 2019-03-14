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
        extends AbstractType {

    public function __construct(NamespaceObject $namespaceObj,\PhpParser\Node\Stmt\Interface_ $obj) {
        parent::__construct($namespaceObj,$obj);
    }

}
