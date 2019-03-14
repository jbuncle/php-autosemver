<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use Exception;

/**
 * Description of NamspaceObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class NamespaceObject {

    private $parent;

    private $namespace;

    private $children = [];

    private $uses = [];

    public function __construct($namespace, NamespaceObject $parent = null) {
        $this->namespace = $namespace;
        $this->parent = $parent;
    }

    public function addAst(array $ast) {
        foreach ($ast as $value) {
            $this->addChild($value);
        }
    }

    private function addChild($child) {

        if ($child instanceof \PhpParser\Node\Stmt\Namespace_) {
            $namespacePath = implode('\\', $child->name->parts);
            $namespace = new NamespaceObject($namespacePath, $this);
            $namespace->addAst($child->stmts);
            $this->children[] = $namespace;
        } if ($child instanceof \PhpParser\Node\Stmt\Use_) {
            if ($child->type === \PhpParser\Node\Stmt\Use_::TYPE_NORMAL) {

                foreach ($child->uses as $useUse) {

                    $this->addUseUse_($useUse);
                }
            } else {
                throw new Exception("Only 'normal' aliases are supported, not type: " . $child->type);
            }
        } else {
            throw new Exception("Unexpected type " . get_class($child));
        }
    }

    private function addClass_(\PhpParser\Node\Stmt\Class_ $class) {
        
    }

    private function addUseUse_(\PhpParser\Node\Stmt\UseUse $useUse) {
        $alias = null;
        if ($useUse->alias) {
            $alias = \is_string($useUse->alias) ? $useUse->alias : $useUse->alias->name;
        }
        $usePath = implode('\\', $useUse->name->parts);

        $this->uses[] = new UseObject($this, $usePath, $alias);
    }

}
