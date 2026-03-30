<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ContractSignature implements LegacySignature {

    /**
     * @var string
     */
    private $kind;

    /**
     * @var TypeReference[]
     */
    private $types;

    /**
     * @param TypeReference[] $types
     */
    public function __construct(string $kind, array $types) {
        $this->kind = $kind;
        $this->types = $types;
    }

    public function toLegacyString(): string {
        return ' ' . $this->kind . ' ' . implode(', ', array_map(function (TypeReference $type): string {
            return $type->toLegacyString();
        }, $this->types));
    }

    public function toIdentityKey(): string {
        return (new ContractIdentity($this->kind, $this->types))->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self
            && $this->kind === $other->kind
            && (new ContractIdentity($this->kind, $this->types))->equals(new ContractIdentity($other->kind, $other->types));
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
