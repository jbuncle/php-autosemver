<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class DefaultValue implements LegacySignature {

    /**
     * @var string
     */
    private $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public function toLegacyString(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
