<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\NamespaceConstantSignature;
use AutomaticSemver\TypeLookup;

class NamespaceConstObject implements SignatureModelProvider {

    /**
     * @var \PhpParser\Node\Stmt\Const_
     */
    private $constObj;

    /**
     * @var TypeLookup
     */
    private $typeLookup;

    public function __construct(\PhpParser\Node\Stmt\Const_ $constObj, TypeLookup $typeLookup) {
        $this->constObj = $constObj;
        $this->typeLookup = $typeLookup;
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
        $signatures = [];

        foreach ($this->constObj->consts as $const) {
            $signatures[] = new NamespaceConstantSignature((string) $const->name, $this->renderValue($const->value));
        }

        return $signatures;
    }

    private function renderValue($value): string {
        if ($value instanceof \PhpParser\Node\Expr\Array_) {
            $items = array_map(function (\PhpParser\Node\Expr\ArrayItem $item): string {
                $renderedValue = $this->renderValue($item->value);
                if ($item->key === null) {
                    return $renderedValue;
                }

                return $this->renderValue($item->key) . ' => ' . $renderedValue;
            }, $value->items);

            return '[' . implode(', ', $items) . ']';
        }
        if ($value instanceof \PhpParser\Node\Scalar\MagicConst) {
            return $value->getName();
        }
        if ($value instanceof \PhpParser\Node\Expr\ConstFetch) {
            return $this->typeLookup->getAbsoluteConstant($value->name);
        }
        if ($value instanceof \PhpParser\Node\Expr\ClassConstFetch) {
            return $this->renderClassName($value->class) . '::' . $value->name;
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryMinus) {
            return '-' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryPlus) {
            return '+' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Scalar\String_) {
            return "'" . $value->value . "'";
        }
        if ($value instanceof \PhpParser\Node\Scalar\LNumber || $value instanceof \PhpParser\Node\Scalar\DNumber) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    private function renderClassName($class): string {
        if ($class instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . ltrim((string) $class, '\\');
        }

        return (string) $class;
    }
}
