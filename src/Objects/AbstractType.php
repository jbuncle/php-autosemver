<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\PrefixedSignature;
use AutomaticSemver\Signature\RawSignature;
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
    private $namespaceObj;

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
        $signatures = [];

        foreach ($this->getObjects() as $object) {
            foreach ($this->getModelsForObject($object) as $signature) {
                $signatures[] = new PrefixedSignature($this->getPath(), $signature);
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
        $sig = '';
        $sig .= (string) $this->obj->name;
        return $sig;
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
            return new ClassMemberObject($stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\ClassConst) {
            return new ClassConstObject($stmt);
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
