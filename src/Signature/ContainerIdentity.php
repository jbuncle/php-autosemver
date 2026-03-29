<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

class ContainerIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $kind;

    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $isAbstract;

    /**
     * @var bool
     */
    private $isFinal;

    public function __construct(string $kind, string $name, bool $isAbstract = false, bool $isFinal = false) {
        $this->kind = $kind;
        $this->name = $name;
        $this->isAbstract = $isAbstract;
        $this->isFinal = $isFinal;
    }

    public function toIdentityKey(): string {
        $parts = [
            'container',
            'kind:' . $this->kind,
            'name:' . $this->name,
        ];

        if ($this->kind === 'class') {
            $parts[] = 'abstract:' . ($this->isAbstract ? '1' : '0');
            $parts[] = 'final:' . ($this->isFinal ? '1' : '0');
        }

        return implode('|', $parts);
    }
}
