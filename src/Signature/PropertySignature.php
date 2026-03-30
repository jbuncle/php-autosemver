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

    /**
     * @var TypeReference|null
     */
    private $type;

    public function __construct(string $name, string $visibility = 'public', bool $isStatic = false, ?TypeReference $type = null) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isStatic = $isStatic;
        $this->type = $type;
    }

    public function toLegacyString(): string {
        $prefix = '';
        if ($this->visibility === 'protected') {
            $prefix .= 'protected ';
        }
        if ($this->isStatic) {
            $prefix .= 'static ';
        }
        if ($this->type !== null) {
            $prefix .= $this->type->toLegacyString() . ' ';
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
        return new PropertyIdentity($this->name, $this->visibility, $this->isStatic, $this->type);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
