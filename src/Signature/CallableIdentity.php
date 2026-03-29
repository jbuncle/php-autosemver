<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class CallableIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $dispatch;

    /**
     * @var string
     */
    private $name;

    /**
     * @var IdentityKey[]
     */
    private $parameters;

    /**
     * @var IdentityKey|null
     */
    private $returnType;

    /**
     * @var string[]
     */
    private $modifiers;

    /**
     * @var bool
     */
    private $wrap;

    /**
     * @param IdentityKey[] $parameters
     * @param string[] $modifiers
     */
    public function __construct(string $dispatch, string $name, array $parameters, ?IdentityKey $returnType, array $modifiers = [], bool $wrap = false) {
        $this->dispatch = $dispatch;
        $this->name = $name;
        $this->parameters = $parameters;
        $this->returnType = $returnType;
        $this->modifiers = $modifiers;
        $this->wrap = $wrap;
    }

    public function toIdentityKey(): string {
        return implode('|', [
            'callable',
            'dispatch:' . $this->dispatch,
            'name:' . $this->name,
            'wrap:' . ($this->wrap ? '1' : '0'),
            'modifiers:' . implode(',', $this->modifiers),
            'params:[' . implode(',', array_map(function (IdentityKey $parameter): string {
                return $parameter->toIdentityKey();
            }, $this->parameters)) . ']',
            $this->returnType ? $this->returnType->toIdentityKey() : 'return:none',
        ]);
    }
}
