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

    /**
     * @var string
     */
    private $visibility;

    public function __construct(string $name, string $value, string $visibility = 'public') {
        $this->name = $name;
        $this->value = $value;
        $this->visibility = $visibility;
    }

    public function toLegacyString(): string {
        $prefix = $this->visibility === 'public' ? '' : $this->visibility . ' ';
        return $prefix . '::' . $this->name . ' = ' . $this->value;
    }

    public function toIdentityKey(): string {
        return $this->getIdentity()->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        if ($other instanceof self) {
            return $this->getIdentity()->equals($other->getIdentity());
        }

        return $this->getIdentity()->equals($other);
    }

    public function getIdentity(): ConstantIdentity {
        return new ConstantIdentity($this->name, $this->value, $this->visibility);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
