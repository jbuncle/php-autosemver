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
        extends AbstractFunction {

    public function __construct(AbstractNamespace $classObject, \PhpParser\Node\FunctionLike $classMethodObj) {
        parent::__construct($classObject, $classMethodObj);
    }

    protected function createSignatureForParams($methodParams, bool $doDefault): string {

        $sig = '';
        $sig .= $this->getName();
        $sig .= '(';
        $sig .= $this->createParameterSignature($methodParams, $doDefault);
        $sig = rtrim($sig, ' ');
        $sig = rtrim($sig, ',');
        $sig .= ')';

        if ($this->functionLikeObj->returnType) {
            $sig .= ':' . $this->getFullType($this->functionLikeObj->returnType);
        }

        return $sig;
    }

}
