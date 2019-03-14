<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of ClassMethodObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassMethodObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\ClassMethod 
     */
    private $classMethodObj;

    function __construct(\PhpParser\Node\Stmt\ClassMethod $classMethodObj) {
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
            $sigs[] = $this->createSignatureForParams($methodParams);
            $lastParam = array_pop($methodParams);
            if (!isset($lastParam->default)) {
                break;
            }
        }
        return $sigs;
    }

    private function createSignatureForParams($methodParams): string {

        $sig = '';
        if ($this->classMethodObj->isStatic()) {
            $sig .= '::';
        } else {
            $sig .= '->';
        }

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
        $sig .= $this->createParameterSignature($methodParams);
        $sig = rtrim($sig, ' ');
        $sig = rtrim($sig, ',');
        $sig .= ')';
        if ($wrap) {
            return '{' . $sig . '}';
        } else {
            return $sig;
        }
    }

    public function getName(): string {
        return (string) $this->classMethodObj->name;
    }

    private function createParameterSignature(array $methodParams) {
        $sig = '';
        foreach ($methodParams as $param) {

            if ($param->variadic) {
                $sig .= "...";
            }
            if ($param->type !== null) {
                $sig .= $this->getFullType($param->type) . '';
            } else {
                $sig .= 'mixed';
            }
            if ($param->default) {
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

    private function getFullType(string $type): string {
        return $type;
    }

}
