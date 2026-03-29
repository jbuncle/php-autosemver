<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ConstantSignature implements LegacySignature {

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $value;

    public function __construct(string $name, string $value) {
        $this->name = $name;
        $this->value = $value;
    }

    public function toLegacyString(): string {
        return '::' . $this->name . ' = ' . $this->value;
    }

    public function toIdentityKey(): string {
        return $this->toLegacyString();
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
