<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * UseObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class UseObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\UseUse
     */
    private $use;

    function __construct(\PhpParser\Node\Stmt\Use_ $use) {
        $this->use = $use;
    }

    public function getSignatures(): array {
        // Use statements don't have a signature
        return [];
    }

    private function getName(\PhpParser\Node\Stmt\UseUse $useUse) {
        if ($useUse->alias) {
            return (string) $useUse->alias;
        } 
        return end($useUse->name->parts);
    }

    public function getAbsoluteType($type) {
        foreach ($this->use->uses as $useUse) {
            $name  = $this->getName($useUse);
            if ($name === $type) {
                return '\\' . implode('\\', $useUse->name->parts);
            }
        }
    }

}
