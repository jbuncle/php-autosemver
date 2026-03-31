<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class TraitUseIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $kind;

    /**
     * @var IdentityKey[]
     */
    private $traits;

    /**
     * @var string|null
     */
    private $method;

    /**
     * @var string|null
     */
    private $newName;

    /**
     * @var string|null
     */
    private $newModifier;

    /**
     * @param IdentityKey[] $traits
     */
    public function __construct(string $kind, array $traits, ?string $method = null, ?string $newName = null, ?string $newModifier = null) {
        $this->kind = $kind;
        $this->traits = $traits;
        $this->method = $method;
        $this->newName = $newName;
        $this->newModifier = $newModifier;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'trait-use',
            'kind:' . $this->kind,
            'traits:[' . implode(',', array_map(function (IdentityKey $trait): string {
                return $trait->toIdentityKey();
            }, $this->traits)) . ']',
            'method:' . ($this->method ?? ''),
            'newName:' . ($this->newName ?? ''),
            'newModifier:' . ($this->newModifier ?? ''),
        ]);
    }

    public function equals(IdentityKey $other): bool {
        if (!$other instanceof self
            || $this->kind !== $other->kind
            || $this->method !== $other->method
            || $this->newName !== $other->newName
            || $this->newModifier !== $other->newModifier
            || count($this->traits) !== count($other->traits)) {
            return false;
        }

        foreach ($this->traits as $index => $trait) {
            if (!$trait->equals($other->traits[$index])) {
                return false;
            }
        }

        return true;
    }
}
