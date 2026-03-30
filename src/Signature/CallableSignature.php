<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class CallableSignature implements LegacySignature {

    /**
     * @var string[]
     */
    private $modifiers;

    /**
     * @var ParameterSignature[]
     */
    private $parameters;

    /**
     * @var string
     */
    private $dispatch;

    /**
     * @var string
     */
    private $name;

    /**
     * @var TypeReference|null
     */
    private $returnType;

    /**
     * @var bool
     */
    private $wrap;

    /**
     * @var bool
     */
    private $returnsReference;

    /**
     * @param ParameterSignature[] $parameters
     * @param string[] $modifiers
     */
    public function __construct(
            string $dispatch,
            string $name,
            array $parameters,
            ?TypeReference $returnType,
            array $modifiers = [],
            bool $wrap = false,
            bool $returnsReference = false
    ) {
        $this->dispatch = $dispatch;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
        $this->modifiers = $modifiers;
        $this->wrap = $wrap;
        $this->returnsReference = $returnsReference;
    }

    public function toLegacyString(): string {
        $signature = '';
        if (!empty($this->modifiers)) {
            $signature .= implode(' ', $this->modifiers) . ' ';
        }

        if ($this->returnsReference) {
            $signature .= '&';
        }

        $signature .= $this->name;
        $signature .= '(' . implode(', ', array_map(function (ParameterSignature $parameter): string {
            return $parameter->toLegacyString();
        }, $this->parameters)) . ')';

        if ($this->returnType !== null) {
            $signature .= ':' . $this->returnType->toLegacyString();
        }

        if ($this->wrap) {
            $signature = '{' . $signature . '}';
        }

        return $this->dispatch . $signature;
    }

    public function toIdentityKey(): string {
        return $this->getIdentity()->toIdentityKey();
    }

    public function equals(IdentityKey $other): bool {
        if ($other instanceof self) {
            return $this->getIdentity()->equals($other->getIdentity());
        }

        return $this->getIdentity()->equals($other);
    }

    public function getIdentity(): CallableIdentity {
        return new CallableIdentity(
            $this->dispatch,
            $this->name,
            array_map(function (ParameterSignature $parameter): ParameterIdentity {
                return $parameter->getIdentity();
            }, $this->parameters),
            $this->returnType,
            $this->modifiers,
            $this->wrap,
            $this->returnsReference
        );
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
