<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of ClassObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Class_ 
     */
    private $classObj;

    public function __construct(\PhpParser\Node\Stmt\Class_ $classObj) {
        $this->classObj = $classObj;
    }

    private function getPath() {
        $sig = '';
        if ($this->classObj->isAbstract() || $this->classObj->isFinal()) {
            $sig .= ($this->classObj->isAbstract()) ? 'abstract ' : '';
            $sig .= ($this->classObj->isFinal()) ? 'final ' : '';
            $sig .= (string) $this->classObj->name;
            return '{' . $sig . '}';
        } else {
            return (string) $this->classObj->name;
        }
    }

    private function hasConstructor(): bool {
        foreach ($this->getCollection()->getObjects() as $object) {
            if ($object instanceof ClassMethodObject && $object->getName() === '__construct') {
                return true;
            }
        }
        return false;
    }

    private function getCollection(): Collection {
        return new Collection($this->classObj->stmts); ;
    }

    public function getSignatures(): array {
        $signatures = [];
        $collection = $this->getCollection();
        if (!$this->hasConstructor()) {
            // Add default constructor
            $signatures[] = $this->getPath() . '->__construct()';
        }
        foreach ($collection->getSignatures() as $signature) {
            $signatures[] = $this->getPath() . $signature;
        }

        return $signatures;
    }

}
