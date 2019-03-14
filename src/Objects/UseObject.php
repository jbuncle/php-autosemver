<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of UseObject
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

}
