<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\IdentityKey;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\NamespaceIdentity;
use AutomaticSemver\Signature\PrefixedSignature;
use AutomaticSemver\Signature\RawSignature;
use AutomaticSemver\TypeLookup;
use Exception;

/**
 * AbstractNamespace
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractNamespace implements SignatureModelProvider, TypeLookup {

    public function getAbsoluteType($typeObj): string {

        // Support null-able (optional) types
        if ($typeObj instanceof \PhpParser\Node\NullableType) {
            $type = $typeObj->type;
            $optional = true;
        } else {
            $type = $typeObj;
            $optional = false;
        }

        $aboluteType = $this->evaluateType($type);
        if ($optional) {
            return '?' . $aboluteType;
        } else {
            return $aboluteType;
        }
    }

    private function evaluateType(string $type) {

        if (strpos($type, '\\') === 0) {
            return $type;
        }
        if ($this->isTypeScalar($type)) {
            return $type;
        }

        return $this->findFullyQualifiedType($type);
    }

    private function isBuiltinConstant(string $constant): bool {
        return in_array(strtolower($constant), ['true', 'false', 'null'], true);
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
            'self',
            'parent',
            'static',
            'iterable',
            'object',
            'void',
        ];
        return in_array($type, $scalars);
    }

    private function findFullyQualifiedType(string $type): string {

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

    public function getAbsoluteConstant($constObj): string {
        if ($constObj instanceof \PhpParser\Node\Name\FullyQualified) {
            return '\\' . ltrim((string) $constObj, '\\');
        }

        $constant = (string) $constObj;
        if ($this->isBuiltinConstant($constant) || defined($constant)) {
            return $constant;
        }

        foreach ($this->getObjects() as $object) {
            if ($object instanceof UseObject) {
                $absoluteConstant = $object->getAbsoluteConstant($constant);
                if ($absoluteConstant !== null) {
                    return $absoluteConstant;
                }
            }
        }

        return $this->getPath() . $constant;
    }

    public abstract function getPath(): string;

    public abstract function getStatements(): array;

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

        foreach ($this->getObjects() as $object) {
            foreach ($this->getModelsForObject($object) as $signature) {
                $signatures[] = $this->prefixSignatureModel($signature);
            }
        }
        return $signatures;
    }

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

    /**
     * @return LegacySignature[]
     */
    private function getModelsForObject(Signatures $object): array {
        if ($object instanceof SignatureModelProvider) {
            return $object->getSignatureModels();
        }

        return array_map(function (string $signature): LegacySignature {
            return new RawSignature($signature);
        }, $object->getSignatures());
    }

    protected function getIdentityPath(): IdentityKey {
        return new NamespaceIdentity($this->getPath());
    }

    protected function prefixSignatureModel(LegacySignature $signature): LegacySignature {
        return new PrefixedSignature($this->getPath(), $signature, $this->getIdentityPath());
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
            \PhpParser\Node\Stmt\Declare_::class,
            \PhpParser\Node\Stmt\Return_::class,
            \PhpParser\Node\Stmt\Unset_::class,
            \PhpParser\Node\Stmt\Global_::class,
            \PhpParser\Node\Stmt\Switch_::class,
            \PhpParser\Node\Stmt\For_::class,
            \PhpParser\Node\Stmt\While_::class,
            \PhpParser\Node\Expr\UnaryMinus::class,
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
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Trait_) {
            return new TraitObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Const_) {
            return new NamespaceConstObject($stmt, $this);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Use_) {
            return new UseObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\GroupUse) {
            return new UseObject($stmt);
        }
        throw new Exception("Unsupported type " . get_class($stmt));
    }

}
