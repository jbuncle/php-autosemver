<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class PrefixedSignature implements LegacySignature {

    /**
     * @var string
     */
    private $legacyPrefix;

    /**
     * @var string
     */
    private $identityPrefix;

    /**
     * @var LegacySignature
     */
    private $signature;

    public function __construct(string $legacyPrefix, LegacySignature $signature, ?string $identityPrefix = null) {
        $this->legacyPrefix = $legacyPrefix;
        $this->identityPrefix = $identityPrefix ?? $legacyPrefix;
        $this->signature = $signature;
    }

    public function toLegacyString(): string {
        return $this->legacyPrefix . $this->signature->toLegacyString();
    }

    public function toIdentityKey(): string {
        return 'prefixed|' . $this->identityPrefix . '|' . $this->signature->toIdentityKey();
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
