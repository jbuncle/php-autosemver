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
                $this->createDefaultValue($param, $doDefault)
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

        if ($param->default instanceof \PhpParser\Node\Expr\Array_) {
            $value = '[';
            foreach ($param->default->items as $item) {
                $value .= $item->value->value;
            }
            $value .= ']';
            return new DefaultValue($value);
        }
        if ($param->default instanceof \PhpParser\Node\Expr\ConstFetch) {
            return new DefaultValue((string) $param->default->name);
        }
        if ($param->default instanceof \PhpParser\Node\Expr\UnaryMinus) {
            return new DefaultValue('-' . $param->default->expr->value);
        }
        if ($param->default instanceof \PhpParser\Node\Expr\UnaryPlus) {
            return new DefaultValue('+' . $param->default->expr->value);
        }

        return new DefaultValue((string) $param->default->value);
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
