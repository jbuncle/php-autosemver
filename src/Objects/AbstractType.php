<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\ContainerIdentity;
use AutomaticSemver\Signature\ContractSignature;
use AutomaticSemver\Signature\IdentityKey;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\PrefixedSignature;
use AutomaticSemver\Signature\RawSignature;
use AutomaticSemver\Signature\TraitUseSignature;
use AutomaticSemver\Signature\TypeReference;
use Exception;

/**
 * AbstractType
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractType
        implements SignatureModelProvider {

    /**
     *
     * @var NamespaceObject
     */
    protected $namespaceObj;

    /**
     *
     * @var \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_
     */
    protected $obj;

    protected function __construct(AbstractNamespace $namespaceObj, $obj) {
        $this->namespaceObj = $namespaceObj;
        $this->obj = $obj;
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
        $signatures = $this->getContractSignatureModels();

        foreach ($this->getObjects() as $object) {
            foreach ($this->getModelsForObject($object) as $signature) {
                $signatures[] = new PrefixedSignature($this->getPath(), $signature, $this->getIdentityPrefix());
            }
        }

        return $signatures;
    }

    public function getObjects(): array {
        $objects = [];
        foreach ($this->obj->stmts as $stmt) {
            $obj = $this->stmtToObj($stmt);
            if ($obj !== null) {
                $objects[] = $obj;
            }
        }
        return $objects;
    }

    protected function getPath() {
        return $this->getTypeName();
    }

    protected function getIdentityPrefix(): IdentityKey {
        return new ContainerIdentity(
            $this->getTypeKind(),
            $this->getTypeName(),
            $this->isAbstractType(),
            $this->isFinalType()
        );
    }

    protected function getTypeName(): string {
        return (string) $this->obj->name;
    }

    /**
     * @return LegacySignature[]
     */
    protected function getContractSignatureModels(): array {
        $signatures = [];

        $extendedTypes = $this->getExtendedTypes();
        if (!empty($extendedTypes)) {
            $signatures[] = new PrefixedSignature(
                $this->getPath(),
                new ContractSignature('extends', $extendedTypes),
                $this->getIdentityPrefix()
            );
        }

        $implementedTypes = $this->getImplementedTypes();
        if (!empty($implementedTypes)) {
            $signatures[] = new PrefixedSignature(
                $this->getPath(),
                new ContractSignature('implements', $implementedTypes),
                $this->getIdentityPrefix()
            );
        }

        foreach ($this->getTraitUseSignatureModels() as $signature) {
            $signatures[] = new PrefixedSignature($this->getPath(), $signature, $this->getIdentityPrefix());
        }

        return $signatures;
    }

    /**
     * @return TypeReference[]
     */
    protected function getExtendedTypes(): array {
        if ($this->obj instanceof \PhpParser\Node\Stmt\Class_ && $this->obj->extends !== null) {
            return [$this->createTypeReferenceFromName($this->obj->extends)];
        }

        if ($this->obj instanceof \PhpParser\Node\Stmt\Interface_) {
            return array_map(function (\PhpParser\Node\Name $name): TypeReference {
                return $this->createTypeReferenceFromName($name);
            }, $this->obj->extends);
        }

        return [];
    }

    /**
     * @return TypeReference[]
     */
    protected function getImplementedTypes(): array {
        if (!$this->obj instanceof \PhpParser\Node\Stmt\Class_) {
            return [];
        }

        return array_map(function (\PhpParser\Node\Name $name): TypeReference {
            return $this->createTypeReferenceFromName($name);
        }, $this->obj->implements);
    }

    protected function createTypeReferenceFromName(\PhpParser\Node\Name $name): TypeReference {
        return new TypeReference($this->namespaceObj->getAbsoluteType((string) $name));
    }

    /**
     * @return TraitUseSignature[]
     */
    private function getTraitUseSignatureModels(): array {
        $signatures = [];

        foreach ($this->obj->stmts as $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\TraitUse) {
                continue;
            }

            $traits = array_map(function (\PhpParser\Node\Name $name): TypeReference {
                return $this->createTypeReferenceFromName($name);
            }, $stmt->traits);
            if (!empty($traits)) {
                $signatures[] = TraitUseSignature::forUse($traits);
            }

            foreach ($stmt->adaptations as $adaptation) {
                if ($adaptation instanceof \PhpParser\Node\Stmt\TraitUseAdaptation\Precedence) {
                    $signatures[] = TraitUseSignature::forPrecedence(
                        $this->createTypeReferenceFromName($adaptation->trait),
                        (string) $adaptation->method,
                        array_map(function (\PhpParser\Node\Name $name): TypeReference {
                            return $this->createTypeReferenceFromName($name);
                        }, $adaptation->insteadof)
                    );
                    continue;
                }

                if ($adaptation instanceof \PhpParser\Node\Stmt\TraitUseAdaptation\Alias) {
                    $signatures[] = TraitUseSignature::forAlias(
                        $adaptation->trait !== null ? $this->createTypeReferenceFromName($adaptation->trait) : null,
                        (string) $adaptation->method,
                        $adaptation->newName !== null ? (string) $adaptation->newName : null,
                        $this->getTraitAliasModifier($adaptation->newModifier)
                    );
                }
            }
        }

        return $signatures;
    }

    private function getTraitAliasModifier(?int $modifier): ?string {
        if ($modifier === null) {
            return null;
        }
        if (($modifier & \PhpParser\Node\Stmt\Class_::MODIFIER_PRIVATE) !== 0) {
            return 'private';
        }
        if (($modifier & \PhpParser\Node\Stmt\Class_::MODIFIER_PROTECTED) !== 0) {
            return 'protected';
        }
        if (($modifier & \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC) !== 0) {
            return 'public';
        }

        return null;
    }

    private function getTypeKind(): string {
        if ($this->obj instanceof \PhpParser\Node\Stmt\Interface_) {
            return 'interface';
        }
        if ($this->obj instanceof \PhpParser\Node\Stmt\Trait_) {
            return 'trait';
        }
        return 'class';
    }

    private function isAbstractType(): bool {
        return $this->obj instanceof \PhpParser\Node\Stmt\Class_ && $this->obj->isAbstract();
    }

    private function isFinalType(): bool {
        return $this->obj instanceof \PhpParser\Node\Stmt\Class_ && $this->obj->isFinal();
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

    /**
     *
     * @param \PhpParser\Node\Stmt\Use_ $stmt
     * @return object
     * @throws Exception
     */
    private function stmtToObj($stmt) {

        if ($stmt instanceof \PhpParser\Node\Stmt\Property) {
            return new ClassMemberObject($stmt, $this->namespaceObj);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\ClassConst) {
            return new ClassConstObject($stmt, $this->namespaceObj);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod) {
            return new ClassMethodObject($this->namespaceObj, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Nop) {
            return null;
        } else if ($stmt instanceof \PhpParser\Node\Stmt\TraitUse) {
            // Don't follow
            return null;
        }
        throw new Exception("Unsupported type " . get_class($stmt));
    }

}
