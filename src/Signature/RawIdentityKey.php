<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class RawIdentityKey implements IdentityKey {

    /**
     * @var string
     */
    private $key;

    public function __construct(string $key) {
        $this->key = $key;
    }

    public function toIdentityKey(): string {
        return $this->key;
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->key === $other->key;
    }
}
