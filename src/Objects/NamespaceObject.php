<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of newPHPClass
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class NamespaceObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Namespace_
     */
    private $namespace;

    public function __construct(\PhpParser\Node\Stmt\Namespace_ $namespace) {
        $this->namespace = $namespace;
    }

    public function getPath(): string {
        return implode('\\', $this->namespace->name->parts);
    }

    public function getSignatures(): array {
        $signatures = [];
        $collection = new Collection($this->namespace->stmts);
        foreach ($collection->getSignatures() as $signature) {
            $signatures[] = $this->getPath() . '\\' . $signature;
        }
        return $signatures;
    }

}
