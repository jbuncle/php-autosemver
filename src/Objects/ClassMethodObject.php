<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\CallableSignature;
use AutomaticSemver\Signature\LegacySignature;
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

    /**
     * @return LegacySignature[]
     */
    public function getSignatureModels(): array {
        if ($this->functionLikeObj->isPrivate()) {
            // Ignore private methods
            return [];
        }
        return parent::getSignatureModels();
    }

    protected function createSignatureModelForParams(array $methodParams, bool $doDefault, $returnType): LegacySignature {
        $modifiers = [];
        $wrap = false;

        if ($this->functionLikeObj->isProtected()) {
            $modifiers[] = 'protected';
            $wrap = true;
        }

        if ($this->functionLikeObj->isFinal()) {
            $modifiers[] = 'final';
            $wrap = true;
        }

        return new CallableSignature(
            $this->functionLikeObj->isStatic() ? '::' : '->',
            $this->getName(),
            $this->createParameterSignatures($methodParams, $doDefault),
            $returnType ? $this->getFullType($returnType) : null,
            $modifiers,
            $wrap
        );
    }

}
