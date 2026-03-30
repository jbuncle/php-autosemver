<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ConstantIdentity implements IdentityKey {

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

    public function toIdentityKey(): string {
        return implode('|', [
            'constant',
            'name:' . $this->name,
            'visibility:' . $this->visibility,
            'value:' . $this->value,
        ]);
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->name === $other->name
            && $this->visibility === $other->visibility
            && $this->value === $other->value;
    }
}
