<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class PrefixedSignature implements LegacySignature {

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var LegacySignature
     */
    private $signature;

    public function __construct(string $prefix, LegacySignature $signature) {
        $this->prefix = $prefix;
        $this->signature = $signature;
    }

    public function toLegacyString(): string {
        return $this->prefix . $this->signature->toLegacyString();
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
