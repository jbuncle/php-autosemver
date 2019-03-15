<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * ClassMethodObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassMethodObject
        implements Signatures {

    /**
     *
     * @var ClassObject
     */
    private $classObject;

    /**
     *
     * @var \PhpParser\Node\Stmt\ClassMethod 
     */
    private $classMethodObj;

    function __construct(AbstractType $classObejct, \PhpParser\Node\Stmt\ClassMethod $classMethodObj) {
        $this->classObject = $classObejct;
        $this->classMethodObj = $classMethodObj;
    }

    public function getSignatures(): array {
        if ($this->classMethodObj->isPrivate()) {
            // Ignore private properties
            return [];
        }

        $methodParams = $this->classMethodObj->params;

        $sigs = [];
        while (!empty($methodParams)) {
            $sigs[] = $this->createSignatureForParams($methodParams, true);
            if (end($methodParams)->default) {
                $sigs[] = $this->createSignatureForParams($methodParams, false);
            }
            $lastParam = array_pop($methodParams);
            if (!isset($lastParam->default)) {
                break;
            }
        }
        return $sigs;
    }

    private function createSignatureForParams($methodParams, bool $doDefault): string {

        $sig = '';


        $wrap = false;

        if ($this->classMethodObj->isProtected()) {
            $sig .= 'protected ';
            $wrap = true;
        }

        if ($this->classMethodObj->isFinal()) {
            $sig .= 'final ';
            $wrap = true;
        }
        $sig .= $this->getName();
        $sig .= '(';
        $sig .= $this->createParameterSignature($methodParams, $doDefault);
        $sig = rtrim($sig, ' ');
        $sig = rtrim($sig, ',');
        $sig .= ')';

        if ($this->classMethodObj->returnType) {
            $sig .= ':' . $this->getFullType($this->classMethodObj->returnType);
        }

        if ($wrap) {
            $sig = '{' . $sig . '}';
        }

        if ($this->classMethodObj->isStatic()) {
            $sig = '::' . $sig;
        } else {
            $sig = '->' . $sig;
        }

        return $sig;
    }

    public function getName(): string {
        return (string) $this->classMethodObj->name;
    }

    private function createParameterSignature(array $methodParams, bool $doDefault): string {
        $sig = '';
        foreach ($methodParams as $param) {

            if ($param->variadic) {
                $sig .= "...";
            }

            $sig .= $this->getFullType($param->type) . '';

            if ($doDefault && $param->default) {
                if ($param->default instanceof \PhpParser\Node\Expr\Array_) {
                    $sig .= ' = [';
                    foreach ($param->default->items as $item) {
                        $sig .= $item->value;
                    }
                    $sig .= ']';
                } else if ($param->default instanceof \PhpParser\Node\Expr\ConstFetch) {
                    $sig .= ' = ' . $param->default->name;
                } else {
                    $sig .= ' = ' . $param->default->value;
                }
            }
            $sig .= ', ';
        }
        return $sig;
    }

    private function getFullType($type): string {
        if (empty($type) || $type === null) {
            return 'mixed';
        }

        if ($type instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . (string) $type;
        }
        return $this->classObject->getAbsoluteType($type);
    }

}
