<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\TypeLookup;

/**
 * AbstractFunction
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractFunction implements Signatures {

    /**
     *
     * @var TypeLookup
     */
    private $parentObject;

    /**
     *
     * @var \PhpParser\Node\FunctionLike
     */
    protected $functionLikeObj;

    public function __construct(TypeLookup $classObject, \PhpParser\Node\FunctionLike $classMethodObj) {
        $this->parentObject = $classObject;
        $this->functionLikeObj = $classMethodObj;
    }

    public function getSignatures(): array {
        /** @var \PhpParser\Node\Param[]|\PhpParser\Param[] $methodParams */
        $methodParams = $this->functionLikeObj->params;

        $sigs = [];

        while (true) {
            $returnType = $this->functionLikeObj->returnType;

            $sigs = array_merge($sigs, $this->createSignatureForParamsAndReturn($methodParams, true, $returnType));

            if (!empty($methodParams) && $this->isOptional(end($methodParams))) {
                // Last param is a default
                $sigs = array_merge($sigs, $this->createSignatureForParamsAndReturn($methodParams, false, $returnType));
            }

            if (empty($methodParams)) {
                break;
            }
            // Remove default from end and continue
            $lastParam = array_pop($methodParams);
            if (!$this->isOptional($lastParam)) {
                break;
            }
        }

        return $sigs;
    }

    private function isOptional($param): bool {
        if ($param->default) {
            return true;
        }

        $type = $this->getFullType($param->type);
        if (strpos($type, '?') === 0) {
            return true;
        }
        
        return false;
    }

    protected function createSignatureForParamsAndReturn(array $methodParams, bool $doDefault, $returnType): array {
        $sigs = [];
        $sigs[] = $this->createSignatureForParams($methodParams, $doDefault, $returnType);
        return $sigs;
    }

    protected abstract function createSignatureForParams(array $methodParams, bool $doDefault, $returnType): string;

    protected function createParameterSignature(array $methodParams, bool $doDefault): string {
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
                        $sig .= $item->value->value;
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

    protected function getFullType($type): string {
        if (empty($type) || $type === null) {
            return 'mixed';
        }

        if ($type instanceof \PhpParser\Node\Name\FullyQualified) {
            return (string) $type;
        }
        return $this->parentObject->getAbsoluteType($type);
    }

    public function getName(): string {
        return (string) $this->functionLikeObj->name;
    }

}
