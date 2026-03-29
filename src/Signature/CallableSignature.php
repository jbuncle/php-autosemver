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
     * @var string[]
     */
    private $parameterTypes;

    /**
     * @param string[] $parameterTypes
     * @param string[] $modifiers
     */
    public function __construct(
            string $dispatch,
            string $name,
            array $parameterTypes,
            ?string $returnType,
            array $modifiers = [],
            bool $wrap = false
    ) {
        $this->dispatch = $dispatch;
        $this->name = $name;
        $this->parameterTypes = $parameterTypes;
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
     * @var string|null
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
        $signature .= '(' . implode(', ', $this->parameterTypes) . ')';

        if ($this->returnType !== null && $this->returnType !== '') {
            $signature .= ':' . $this->returnType;
        }

        if ($this->wrap) {
            $signature = '{' . $signature . '}';
        }

        return $this->dispatch . $signature;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
