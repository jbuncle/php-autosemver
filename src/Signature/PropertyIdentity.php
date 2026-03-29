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

    public function __construct(string $name, string $visibility = 'public', bool $isStatic = false) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isStatic = $isStatic;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'property',
            'name:' . $this->name,
            'visibility:' . $this->visibility,
            'static:' . ($this->isStatic ? '1' : '0'),
        ]);
    }
}
