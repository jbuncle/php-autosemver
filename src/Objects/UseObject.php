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
class UseObject implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Use_|\PhpParser\Node\Stmt\GroupUse
     */
    private $use;

    public function __construct($use) {
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

    private function getUseType(\PhpParser\Node\Stmt\UseUse $useUse): int {
        if ($useUse->type !== \PhpParser\Node\Stmt\Use_::TYPE_UNKNOWN) {
            return $useUse->type;
        }

        return $this->use->type;
    }

    private function getAbsoluteImportName(\PhpParser\Node\Stmt\UseUse $useUse): string {
        if ($this->use instanceof \PhpParser\Node\Stmt\GroupUse) {
            return '\\' . $this->use->prefix . '\\' . $useUse->name;
        }

        return '\\' . implode('\\', $useUse->name->parts);
    }

    public function getAbsoluteType($type) {
        foreach ($this->use->uses as $useUse) {
            if ($this->getUseType($useUse) !== \PhpParser\Node\Stmt\Use_::TYPE_NORMAL) {
                continue;
            }

            $name = $this->getName($useUse);
            if ($name === $type) {
                return $this->getAbsoluteImportName($useUse);
            }
        }
    }

}
