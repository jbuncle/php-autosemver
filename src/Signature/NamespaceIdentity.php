<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class NamespaceIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    public function toIdentityKey(): string {
        return 'namespace:' . $this->path;
    }
}
