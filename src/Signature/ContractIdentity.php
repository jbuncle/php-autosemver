<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ContractIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $kind;

    /**
     * @var IdentityKey[]
     */
    private $types;

    /**
     * @param IdentityKey[] $types
     */
    public function __construct(string $kind, array $types) {
        $this->kind = $kind;
        $this->types = $types;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'contract',
            'kind:' . $this->kind,
            'types:[' . implode(',', array_map(function (IdentityKey $type): string {
                return $type->toIdentityKey();
            }, $this->getNormalisedTypes())) . ']',
        ]);
    }

    public function equals(IdentityKey $other): bool {
        if (!$other instanceof self || $this->kind !== $other->kind || count($this->types) !== count($other->types)) {
            return false;
        }

        $left = $this->getNormalisedTypes();
        $right = $other->getNormalisedTypes();
        foreach ($left as $index => $type) {
            if (!$type->equals($right[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return IdentityKey[]
     */
    private function getNormalisedTypes(): array {
        $types = $this->types;
        usort($types, function (IdentityKey $left, IdentityKey $right): int {
            return strcmp($left->toIdentityKey(), $right->toIdentityKey());
        });

        return $types;
    }
}
