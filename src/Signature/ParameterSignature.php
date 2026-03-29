<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ParameterSignature implements LegacySignature {

    /**
     * @var TypeReference
     */
    private $type;

    /**
     * @var bool
     */
    private $variadic;

    /**
     * @var DefaultValue|null
     */
    private $defaultValue;

    public function __construct(TypeReference $type, bool $variadic = false, ?DefaultValue $defaultValue = null) {
        $this->type = $type;
        $this->variadic = $variadic;
        $this->defaultValue = $defaultValue;
    }

    public function toLegacyString(): string {
        $parameter = '';
        if ($this->variadic) {
            $parameter .= '...';
        }

        $parameter .= $this->type->toLegacyString();

        if ($this->defaultValue !== null) {
            $parameter .= ' = ' . $this->defaultValue->toLegacyString();
        }

        return $parameter;
    }

    public function __toString(): string {
        return $this->toLegacyString();
    }
}
