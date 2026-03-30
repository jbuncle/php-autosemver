<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\TypeLookup;

/**
 * PropertyObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassMemberObject
        extends PropertyObject {

    public function __construct(\PhpParser\Node\Stmt\Property $propertyObj, TypeLookup $typeLookup) {
        parent::__construct($propertyObj, $typeLookup);
    }
}
