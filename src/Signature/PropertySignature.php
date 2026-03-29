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
    private $prefix;

    public function __construct(string $name, string $prefix = '') {
        $this->name = $name;
        $this->prefix = $prefix;
    }

    public function toLegacyString(): string {
        return $this->prefix . '$' . $this->name;
    }

    public function toIdentityKey(): string {
        return 'property|' . $this->prefix . '|$' . $this->name;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
