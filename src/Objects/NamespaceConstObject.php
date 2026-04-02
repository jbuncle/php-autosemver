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
        if ($value instanceof \PhpParser\Node\Expr\ArrayDimFetch) {
            return $this->renderArrayDimFetch($value);
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryMinus) {
            return '-' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Expr\UnaryPlus) {
            return '+' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Expr\BooleanNot) {
            return '!' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Expr\BitwiseNot) {
            return '~' . $this->renderValue($value->expr);
        }
        if ($value instanceof \PhpParser\Node\Expr\Ternary) {
            return $this->renderTernaryExpression($value);
        }
        if ($value instanceof \PhpParser\Node\Expr\BinaryOp) {
            return $this->renderBinaryExpression($value);
        }
        if ($value instanceof \PhpParser\Node\Scalar\String_) {
            return "'" . $value->value . "'";
        }
        if ($value instanceof \PhpParser\Node\Scalar\LNumber || $value instanceof \PhpParser\Node\Scalar\DNumber) {
            return (string) $value->value;
        }

        return (string) $value;
    }



    private function renderArrayDimFetch(\PhpParser\Node\Expr\ArrayDimFetch $value): string {
        $dimension = $value->dim === null ? '' : $this->renderValue($value->dim);

        return $this->renderArrayDimTarget($value->var) . '[' . $dimension . ']';
    }

    private function renderArrayDimTarget($value): string {
        $rendered = $this->renderValue($value);
        if ($value instanceof \PhpParser\Node\Expr\BinaryOp || $value instanceof \PhpParser\Node\Expr\Ternary) {
            return '(' . $rendered . ')';
        }

        return $rendered;
    }

    private function renderBinaryExpression(\PhpParser\Node\Expr\BinaryOp $value): string {
        $operator = $this->getBinaryOperator($value);
        if ($operator === null) {
            return (string) $value;
        }

        return $this->renderBinaryOperand($value->left) . ' ' . $operator . ' ' . $this->renderBinaryOperand($value->right);
    }

    private function renderBinaryOperand($value): string {
        $rendered = $this->renderValue($value);
        if ($value instanceof \PhpParser\Node\Expr\BinaryOp) {
            return '(' . $rendered . ')';
        }

        return $rendered;
    }

    private function getBinaryOperator(\PhpParser\Node\Expr\BinaryOp $value): ?string {
        $operators = [
            \PhpParser\Node\Expr\BinaryOp\Concat::class => '.',
            \PhpParser\Node\Expr\BinaryOp\Coalesce::class => '??',
            \PhpParser\Node\Expr\BinaryOp\BooleanAnd::class => '&&',
            \PhpParser\Node\Expr\BinaryOp\BooleanOr::class => '||',
            \PhpParser\Node\Expr\BinaryOp\LogicalAnd::class => 'and',
            \PhpParser\Node\Expr\BinaryOp\LogicalOr::class => 'or',
            \PhpParser\Node\Expr\BinaryOp\LogicalXor::class => 'xor',
            \PhpParser\Node\Expr\BinaryOp\Equal::class => '==',
            \PhpParser\Node\Expr\BinaryOp\NotEqual::class => '!=',
            \PhpParser\Node\Expr\BinaryOp\Identical::class => '===',
            \PhpParser\Node\Expr\BinaryOp\NotIdentical::class => '!==',
            \PhpParser\Node\Expr\BinaryOp\Greater::class => '>',
            \PhpParser\Node\Expr\BinaryOp\GreaterOrEqual::class => '>=',
            \PhpParser\Node\Expr\BinaryOp\Smaller::class => '<',
            \PhpParser\Node\Expr\BinaryOp\SmallerOrEqual::class => '<=',
            \PhpParser\Node\Expr\BinaryOp\Spaceship::class => '<=>',
            \PhpParser\Node\Expr\BinaryOp\Pow::class => '**',
            \PhpParser\Node\Expr\BinaryOp\Plus::class => '+',
            \PhpParser\Node\Expr\BinaryOp\Minus::class => '-',
            \PhpParser\Node\Expr\BinaryOp\Mul::class => '*',
            \PhpParser\Node\Expr\BinaryOp\Div::class => '/',
            \PhpParser\Node\Expr\BinaryOp\Mod::class => '%',
            \PhpParser\Node\Expr\BinaryOp\BitwiseAnd::class => '&',
            \PhpParser\Node\Expr\BinaryOp\BitwiseOr::class => '|',
            \PhpParser\Node\Expr\BinaryOp\BitwiseXor::class => '^',
            \PhpParser\Node\Expr\BinaryOp\ShiftLeft::class => '<<',
            \PhpParser\Node\Expr\BinaryOp\ShiftRight::class => '>>',
        ];

        foreach ($operators as $class => $operator) {
            if ($value instanceof $class) {
                return $operator;
            }
        }

        return null;
    }


    private function renderTernaryExpression(\PhpParser\Node\Expr\Ternary $value): string {
        $condition = $this->renderBinaryOperand($value->cond);
        if ($value->if === null) {
            return $condition . ' ?: ' . $this->renderBinaryOperand($value->else);
        }

        return $condition . ' ? ' . $this->renderBinaryOperand($value->if) . ' : ' . $this->renderBinaryOperand($value->else);
    }

    private function renderClassName($class): string {
        if ($class instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . ltrim((string) $class, '\\');
        }

        return $this->typeLookup->getAbsoluteType($class);
    }
}
