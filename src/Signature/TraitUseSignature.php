<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class TraitUseSignature implements LegacySignature {

    /**
     * @var TraitUseIdentity
     */
    private $identity;

    /**
     * @var string
     */
    private $legacyString;

    private function __construct(TraitUseIdentity $identity, string $legacyString) {
        $this->identity = $identity;
        $this->legacyString = $legacyString;
    }

    /**
     * @param TypeReference[] $traits
     */
    public static function forUse(array $traits): self {
        return new self(
            new TraitUseIdentity('use', $traits),
            ' use ' . implode(', ', array_map(function (TypeReference $trait): string {
                return $trait->toLegacyString();
            }, $traits))
        );
    }

    /**
     * @param TypeReference[] $insteadOfTraits
     */
    public static function forPrecedence(TypeReference $selectedTrait, string $method, array $insteadOfTraits): self {
        return new self(
            new TraitUseIdentity('precedence', array_merge([$selectedTrait], $insteadOfTraits), $method),
            ' use ' . $selectedTrait->toLegacyString() . '::' . $method . ' insteadof '
                . implode(', ', array_map(function (TypeReference $trait): string {
                    return $trait->toLegacyString();
                }, $insteadOfTraits))
        );
    }

    public static function forAlias(?TypeReference $trait, string $method, ?string $newName, ?string $newModifier): self {
        $traits = [];
        $left = $method;
        if ($trait !== null) {
            $traits[] = $trait;
            $left = $trait->toLegacyString() . '::' . $method;
        }

        $right = [];
        if ($newModifier !== null) {
            $right[] = $newModifier;
        }
        if ($newName !== null) {
            $right[] = $newName;
        }

        return new self(
            new TraitUseIdentity('alias', $traits, $method, $newName, $newModifier),
            ' use ' . $left . ' as' . (empty($right) ? '' : ' ' . implode(' ', $right))
        );
    }

    public function toLegacyString(): string {
        return $this->legacyString;
    }

    public function toIdentityKey(): string {
        return $this->identity->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self && $this->identity->equals($other->identity);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
