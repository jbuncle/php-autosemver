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

    public function __construct(string $name, string $value) {
        $this->name = $name;
        $this->value = $value;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'constant',
            'name:' . $this->name,
            'value:' . $this->value,
        ]);
    }
}
