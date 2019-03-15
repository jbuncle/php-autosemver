<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Objects\NamespaceObject;
use AutomaticSemver\Objects\Signatures;
use Exception;

/**
 * Collection
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class Collection
        implements Signatures {

    private $stmts;

    public function __construct(array $stmts) {
        $this->stmts = $stmts;
    }

    public function getSignatures(): array {
        $signatures = [];
        foreach ($this->getObjects() as $obj) {
            if ($obj === null) {
                continue;
            }
            $childSignatures = $obj->getSignatures();
            if (!is_array($childSignatures)) {
                throw new Exception("Not array sigs..." . ' ' . var_export($childSignatures) . get_class($obj));
            }
            foreach ($childSignatures as $childSignature) {
                $signatures[] = $childSignature;
            }
        }
        return $signatures;
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

    public function getObjects(): array {
        $objects = [];
        foreach ($this->stmts as $stmt) {
            $objects[] = $this->stmtToObjs($stmt);
        }
        return $objects;
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
            return new NamespaceObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Function_) {
            return new FunctionObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Property) {
            return new PropertyObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
            return new ClassObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Interface_) {
            return new InterfaceObject($stmt);
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
