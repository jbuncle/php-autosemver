<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class NamespaceConstantSignature implements LegacySignature {

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
        return $this->name . ' = ' . $this->value;
    }

    public function toIdentityKey(): string {
        return (new NamespaceConstantIdentity($this->name, $this->value))->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->name === $other->name
            && $this->value === $other->value;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
