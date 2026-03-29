<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\Signature\IdentityKey;

class ReportIdentity implements IdentityKey {

    /**
     * @var string
     */
    private $label;

    public function __construct(string $label) {
        $this->label = $label;
    }

    public function toIdentityKey(): string {
        return 'report:' . $this->label;
    }

    public function equals(IdentityKey $other): bool {
        return $other instanceof self && $this->label === $other->label;
    }
}
