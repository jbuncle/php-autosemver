<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class TypeReference implements LegacySignature {

    /**
     * @var string
     */
    private $type;

    public function __construct(string $type) {
        $this->type = $type;
    }

    public function toLegacyString(): string {
        return $this->type;
    }

    public function toIdentityKey(): string {
        return $this->toLegacyString();
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
