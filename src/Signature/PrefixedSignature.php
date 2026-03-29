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
     * @var IdentityKey
     */
    private $identityPrefix;

    /**
     * @var LegacySignature
     */
    private $signature;

    public function __construct(string $legacyPrefix, LegacySignature $signature, ?IdentityKey $identityPrefix = null) {
        $this->legacyPrefix = $legacyPrefix;
        $this->identityPrefix = $identityPrefix ?? new RawIdentityKey($legacyPrefix);
        $this->signature = $signature;
    }

    public function toLegacyString(): string {
        return $this->legacyPrefix . $this->signature->toLegacyString();
    }

    public function toIdentityKey(): string {
        return 'prefixed|' . $this->identityPrefix->toIdentityKey() . '|' . $this->signature->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->identityPrefix->equals($other->identityPrefix)
            && $this->signature->equals($other->signature);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
