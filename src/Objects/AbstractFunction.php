<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\DefaultValue;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\ParameterSignature;
use AutomaticSemver\Signature\TypeReference;
use AutomaticSemver\TypeLookup;

/**
 * AbstractFunction
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractFunction implements SignatureModelProvider {

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
     * @return ParameterSignature[]
     */
    protected function createParameterSignatures(array $methodParams, bool $doDefault): array {
        $parameters = [];
        foreach ($methodParams as $param) {
            $parameters[] = new ParameterSignature(
                $this->getTypeReference($param->type),
                (bool) $param->variadic,
                $this->createDefaultValue($param, $doDefault),
                (bool) $param->byRef
            );
        }
        return $parameters;
    }

    protected function getTypeReference($type): TypeReference {
        return new TypeReference($this->getFullType($type));
    }

    private function createDefaultValue($param, bool $doDefault): ?DefaultValue {
        if (!$doDefault || !$param->default) {
            return null;
        }

        return new DefaultValue($this->renderDefaultExpression($param->default));
    }

    private function renderDefaultExpression($expression): string {
        if ($expression instanceof \PhpParser\Node\Expr\Array_) {
            $items = array_map(function (\PhpParser\Node\Expr\ArrayItem $item): string {
                $value = $this->renderDefaultExpression($item->value);
                if ($item->key === null) {
                    return $value;
                }

                return $this->renderDefaultExpression($item->key) . ' => ' . $value;
            }, $expression->items);

            return '[' . implode(', ', $items) . ']';
        }
        if ($expression instanceof \PhpParser\Node\Expr\ConstFetch) {
            return (string) $expression->name;
        }
        if ($expression instanceof \PhpParser\Node\Expr\ClassConstFetch) {
            return $this->renderDefaultClassName($expression->class) . '::' . $expression->name;
        }
        if ($expression instanceof \PhpParser\Node\Expr\UnaryMinus) {
            return '-' . $this->renderDefaultExpression($expression->expr);
        }
        if ($expression instanceof \PhpParser\Node\Expr\UnaryPlus) {
            return '+' . $this->renderDefaultExpression($expression->expr);
        }
        if ($expression instanceof \PhpParser\Node\Scalar\String_) {
            return "'" . $expression->value . "'";
        }
        if ($expression instanceof \PhpParser\Node\Scalar\LNumber || $expression instanceof \PhpParser\Node\Scalar\DNumber) {
            return (string) $expression->value;
        }

        return (string) $expression;
    }

    private function renderDefaultClassName($class): string {
        if ($class instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . ltrim((string) $class, '\\');
        }

        return $this->parentObject->getAbsoluteType($class);
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
