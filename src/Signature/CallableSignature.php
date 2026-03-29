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
     * @param ParameterSignature[] $parameters
     * @param string[] $modifiers
     */
    public function __construct(
            string $dispatch,
            string $name,
            array $parameters,
            ?TypeReference $returnType,
            array $modifiers = [],
            bool $wrap = false
    ) {
        $this->dispatch = $dispatch;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
        $this->modifiers = $modifiers;
        $this->wrap = $wrap;
    }

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

    public function toLegacyString(): string {
        $signature = '';
        if (!empty($this->modifiers)) {
            $signature .= implode(' ', $this->modifiers) . ' ';
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
        return implode('|', [
            'callable',
            'dispatch:' . $this->dispatch,
            'name:' . $this->name,
            'wrap:' . ($this->wrap ? '1' : '0'),
            'modifiers:' . implode(',', $this->modifiers),
            'params:[' . implode(',', array_map(function (ParameterSignature $parameter): string {
                return $parameter->toIdentityKey();
            }, $this->parameters)) . ']',
            $this->returnType ? $this->returnType->toIdentityKey() : 'return:none',
        ]);
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
