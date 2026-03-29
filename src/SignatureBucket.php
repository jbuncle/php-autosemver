<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\Signature\IdentityKey;

class SignatureBucket {

    /**
     * @var IdentityKey
     */
    private $identity;

    /**
     * @var string[]
     */
    private $displays;

    /**
     * @param string[] $displays
     */
    public function __construct(IdentityKey $identity, array $displays = []) {
        $this->identity = $identity;
        $this->displays = [];
        foreach ($displays as $display) {
            $this->addDisplay($display);
        }
    }

    public function getIdentity(): IdentityKey {
        return $this->identity;
    }

    /**
     * @return string[]
     */
    public function getDisplays(): array {
        return $this->displays;
    }

    public function addDisplay(string $display): void {
        if (!in_array($display, $this->displays, true)) {
            $this->displays[] = $display;
        }
    }

    public function matches(IdentityKey $identity): bool {
        return $this->identity->equals($identity) || $identity->equals($this->identity);
    }
}
