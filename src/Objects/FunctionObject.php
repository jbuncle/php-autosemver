<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\CallableSignature;
use AutomaticSemver\Signature\LegacySignature;

/**
 * FunctionObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class FunctionObject
        extends AbstractFunction {

    public function __construct(AbstractNamespace $classObject, \PhpParser\Node\FunctionLike $classMethodObj) {
        parent::__construct($classObject, $classMethodObj);
    }

    protected function createSignatureModelForParams(array $methodParams, bool $doDefault, $returnType): LegacySignature {
        return new CallableSignature(
            '',
            $this->getName(),
            $this->createParameterTypes($methodParams, $doDefault),
            $returnType ? $this->getFullType($returnType) : null
        );
    }

}
