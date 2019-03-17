<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\TypeLookup;

/**
 * ClassMethodObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassMethodObject
        extends AbstractFunction {

    public function __construct(TypeLookup $classObject, \PhpParser\Node\Stmt\ClassMethod $classMethodObj) {
        parent::__construct($classObject, $classMethodObj);
    }

    public function getSignatures(): array {
        if ($this->functionLikeObj->isPrivate()) {
            // Ignore private properties
            return [];
        }
        return parent::getSignatures();
    }

    protected function createSignatureForParams(array $methodParams, bool $doDefault, $returnType ): string {

        $sig = '';
        $wrap = false;

        if ($this->functionLikeObj->isProtected()) {
            $sig .= 'protected ';
            $wrap = true;
        }

        if ($this->functionLikeObj->isFinal()) {
            $sig .= 'final ';
            $wrap = true;
        }
        $sig .= $this->getName();
        $sig .= '(';
        $sig .= $this->createParameterSignature($methodParams, $doDefault);
        $sig = rtrim($sig, ' ');
        $sig = rtrim($sig, ',');
        $sig .= ')';

        if ($returnType) {
            $sig .= ':' . $this->getFullType($returnType);
        }

        if ($wrap) {
            $sig = '{' . $sig . '}';
        }

        if ($this->functionLikeObj->isStatic()) {
            $sig = '::' . $sig;
        } else {
            $sig = '->' . $sig;
        }

        return $sig;
    }

}
