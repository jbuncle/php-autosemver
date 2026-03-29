<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;
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
        return array_map(function (LegacySignature $signature): string {
            return $signature->toLegacyString();
        }, $this->getSignatureModels());
    }

    /**
     * @return LegacySignature[]
     */
    public function getSignatureModels(): array {
        $methodParams = $this->functionLikeObj->params;

        $sigs = [];

        while (true) {
            $returnType = $this->functionLikeObj->returnType;

            $sigs = array_merge($sigs, $this->createSignatureModelsForParamsAndReturn($methodParams, true, $returnType));

            if (!empty($methodParams) && end($methodParams)->default) {
                // Last param is a default
                $sigs = array_merge($sigs, $this->createSignatureModelsForParamsAndReturn($methodParams, false, $returnType));
            }

            if (empty($methodParams)) {
                break;
            }
            // Remove default from end and continue
            $lastParam = array_pop($methodParams);
            if (!isset($lastParam->default)) {
                break;
            }
        }

        return $sigs;
    }

    /**
     * @return LegacySignature[]
     */
    protected function createSignatureModelsForParamsAndReturn(array $methodParams, bool $doDefault, $returnType): array {
        return [$this->createSignatureModelForParams($methodParams, $doDefault, $returnType)];
    }

    protected abstract function createSignatureModelForParams(array $methodParams, bool $doDefault, $returnType): LegacySignature;

    /**
     * @return string[]
     */
    protected function createParameterTypes(array $methodParams, bool $doDefault): array {
        $types = [];
        foreach ($methodParams as $param) {
            $type = '';

            if ($param->variadic) {
                $type .= '...';
            }

            $type .= $this->getFullType($param->type);

            if ($doDefault && $param->default) {
                if ($param->default instanceof \PhpParser\Node\Expr\Array_) {
                    $type .= ' = [';
                    foreach ($param->default->items as $item) {
                        $type .= $item->value->value;
                    }
                    $type .= ']';
                } else if ($param->default instanceof \PhpParser\Node\Expr\ConstFetch) {
                    $type .= ' = ' . $param->default->name;
                } else if ($param->default instanceof \PhpParser\Node\Expr\UnaryMinus) {
                    $type .= ' = -' . $param->default->expr->value;
                } else if ($param->default instanceof \PhpParser\Node\Expr\UnaryPlus) {
                    $type .= ' = +' . $param->default->expr->value;
                } else {
                    $type .= ' = ' . $param->default->value;
                }
            }

            $types[] = $type;
        }
        return $types;
    }

    protected function getFullType($type): string {
        if (empty($type) || $type === null) {
            return 'mixed';
        }

        if ($type instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . ltrim((string) $type, '\\');
        }
        return $this->parentObject->getAbsoluteType($type);
    }

    public function getName(): string {
        return (string) $this->functionLikeObj->name;
    }

}
