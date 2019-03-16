<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\TypeLookup;
use Exception;

/**
 * AbstractNamespace
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractNamespace
        implements Signatures, TypeLookup {

    public function getAbsoluteType(string $type): string {
        if (strpos($type, '\\') === 0) {
            return $type;
        }
        if ($this->isTypeScalar($type)) {
            return $type;
        }
        return $this->findAbsoluteType($type);
    }

    private function isTypeScalar(string $type): bool {
        $scalars = [
            'string',
            'callable',
            'int',
            'integer',
            'float',
            'bool',
            'boolean',
            'array',
        ];
        return in_array($type, $scalars);
    }

    private function findAbsoluteType(string $type): string {

        foreach ($this->getObjects() as $object) {
            if ($object instanceof UseObject) {
                $absoluteType = $object->getAbsoluteType($type);
                if ($absoluteType !== null) {
                    return $absoluteType;
                }
            }
        }

        return $this->getPath() . $type;
    }

    public abstract function getPath(): string;

    public abstract function getStatements(): array;
    public abstract function getSignatures(): array;


    public function getObjects(): array {
        $objects = [];
        foreach ($this->getStatements() as $stmt) {
            $obj = $this->stmtToObjs($stmt);
            if ($obj !== null) {
                $objects[] = $this->stmtToObjs($stmt);
            }
        }
        return $objects;
    }

    private function isIgnorable($stmt) {
        $class = get_class($stmt);
        $ignoreables = [
            \PhpParser\Node\Stmt\If_::class,
            \PhpParser\Node\Stmt\InlineHTML::class,
            \PhpParser\Node\Stmt\Echo_::class,
            \PhpParser\Node\Stmt\Foreach_::class,
            \PhpParser\Node\Stmt\Nop::class,
            \PhpParser\Node\Stmt\Expression::class,
        ];
        return in_array($class, $ignoreables);
    }

    /**
     * 
     * @param \PhpParser\Node\Stmt\Use_ $stmt
     * @return Signatures[]
     * @throws Exception
     */
    private function stmtToObjs($stmt) {
        if ($this->isIgnorable($stmt)) {
            return null;
        }

        if ($stmt instanceof \PhpParser\Node\Stmt\Namespace_) {
            return new NamespaceObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Property) {
            return new PropertyObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Function_) {
            return new FunctionObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
            return new ClassObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Interface_) {
            return new InterfaceObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
            if ($stmt->type === \PhpParser\Node\Stmt\Use_::TYPE_NORMAL) {
                return new UseObject($stmt);
            } else {
                throw new Exception("Only 'normal' aliases are supported, not type: " . $stmt->type);
            }
        }
        throw new Exception("Unsupported type " . get_class($stmt));
    }

}