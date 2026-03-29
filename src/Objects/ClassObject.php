<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\CallableSignature;
use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\PrefixedSignature;

/**
 * ClassObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class ClassObject
        extends AbstractType {

    public function __construct(AbstractNamespace $namespaceObj, \PhpParser\Node\Stmt\Class_ $obj) {
        parent::__construct($namespaceObj, $obj);
    }

    /**
     * @return LegacySignature[]
     */
    public function getSignatureModels(): array {
        $signatures = parent::getSignatureModels();
        if (!$this->hasConstructor()) {
            // Add default constructor
            $signatures[] = new PrefixedSignature($this->getPath(), new CallableSignature('->', '__construct', [], null), $this->getIdentityPrefix());
        }
        return $signatures;
    }

    protected function getPath() {
        $sig = '';
        if ($this->obj->isAbstract() || $this->obj->isFinal()) {
            $sig .= ($this->obj->isAbstract()) ? 'abstract ' : '';
            $sig .= ($this->obj->isFinal()) ? 'final ' : '';
            $sig .= parent::getPath();
            return '{' . $sig . '}';
        } else {
            return (string) $this->obj->name;
        }
    }

    private function hasConstructor(): bool {
        foreach ($this->getObjects() as $object) {
            if ($object instanceof ClassMethodObject && $object->getName() === '__construct') {
                return true;
            }
        }
        return false;
    }

}
