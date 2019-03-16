<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * NamespaceObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class NamespaceObject
        extends AbstractNamespace {

    /**
     *
     * @var AbstractNamespace
     */
    private $parentNamespace;

    /**
     *
     * @var \PhpParser\Node\Stmt\Namespace_
     */
    private $namespace;

    public function __construct(AbstractNamespace $parentNamespace, \PhpParser\Node\Stmt\Namespace_ $namespace) {
        $this->parentNamespace = $parentNamespace;
        $this->namespace = $namespace;
    }

    public function getPath(): string {
        return $this->parentNamespace->getPath() . implode('\\', $this->namespace->name->parts) . '\\';
    }

    public function getStatements(): array {
        return $this->namespace->stmts;
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

}
