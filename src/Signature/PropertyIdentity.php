<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class PropertyIdentity implements IdentityKey {

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
     * @var IdentityKey|null
     */
    private $type;

    public function __construct(string $name, string $visibility = 'public', bool $isStatic = false, ?IdentityKey $type = null) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isStatic = $isStatic;
        $this->type = $type;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'property',
            'name:' . $this->name,
            'visibility:' . $this->visibility,
            'static:' . ($this->isStatic ? '1' : '0'),
            $this->type ? $this->type->toIdentityKey() : 'type:none',
        ]);
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->name === $other->name
            && $this->visibility === $other->visibility
            && $this->isStatic === $other->isStatic
            && $this->typeMatches($other->type);
    }

    private function typeMatches(?IdentityKey $otherType): bool {
        if ($this->type === null || $otherType === null) {
            return $this->type === $otherType;
        }

        return $this->type->equals($otherType);
    }
}
