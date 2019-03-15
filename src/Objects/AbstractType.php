<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use Exception;

/**
 *  AbstractType
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
abstract class AbstractType
        implements Signatures {

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

    protected function __construct(NamespaceObject $namespaceObj, $obj) {
        $this->namespaceObj = $namespaceObj;
        $this->obj = $obj;
    }

    public function getAbsoluteType(string $type): string {
        return $this->namespaceObj->getAbsoluteType($type);
    }

    public function getSignatures(): array {
        $signatures = [];

        foreach ($this->getObjects() as $object) {
            foreach ($object->getSignatures() as $signature) {
                $signatures[] = $this->getPath() . $signature;
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
            return new ClassMethodObject($this, $stmt);
        } else if ($stmt instanceof \PhpParser\Node\Stmt\Nop) {
            return null;
        }
        throw new Exception("Unsupported type " . get_class($stmt));
    }

}
