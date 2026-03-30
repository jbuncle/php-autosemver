<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ParameterIdentity implements IdentityKey {

    /**
     * @var IdentityKey
     */
    private $type;

    /**
     * @var bool
     */
    private $variadic;

    /**
     * @var bool
     */
    private $byReference;

    /**
     * @var IdentityKey|null
     */
    private $defaultValue;

    public function __construct(IdentityKey $type, bool $variadic = false, ?IdentityKey $defaultValue = null, bool $byReference = false) {
        $this->type = $type;
        $this->variadic = $variadic;
        $this->defaultValue = $defaultValue;
        $this->byReference = $byReference;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'param',
            $this->variadic ? 'variadic:1' : 'variadic:0',
            $this->byReference ? 'byref:1' : 'byref:0',
            $this->type->toIdentityKey(),
            $this->defaultValue ? $this->defaultValue->toIdentityKey() : 'default:none',
        ]);
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->variadic === $other->variadic
            && $this->byReference === $other->byReference
            && $this->type->equals($other->type)
            && $this->identityMatches($this->defaultValue, $other->defaultValue);
    }

    private function identityMatches(?IdentityKey $left, ?IdentityKey $right): bool {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $left->equals($right);
    }
}
