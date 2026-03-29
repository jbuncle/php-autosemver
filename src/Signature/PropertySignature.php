<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class PropertySignature implements LegacySignature {

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $visibility;

    /**
     * @var bool
     */
    private $isStatic;

    public function __construct(string $name, string $visibility = 'public', bool $isStatic = false) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isStatic = $isStatic;
    }

    public function toLegacyString(): string {
        $prefix = '';
        if ($this->visibility === 'protected') {
            $prefix .= 'protected ';
        }
        if ($this->isStatic) {
            $prefix .= 'static ';
        }

        return $prefix . '$' . $this->name;
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

    public function getIdentity(): PropertyIdentity {
        return new PropertyIdentity($this->name, $this->visibility, $this->isStatic);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
