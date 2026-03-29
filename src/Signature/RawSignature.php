<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class RawSignature implements LegacySignature {

    /**
     * @var string
     */
    private $signature;

    public function __construct(string $signature) {
        $this->signature = $signature;
    }

    public function toLegacyString(): string {
        return $this->signature;
    }

    public function toIdentityKey(): string {
        return $this->toLegacyString();
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->signature === $other->signature;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
