<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class RevisionRange {

    /**
     * @var string
     */
    private $from;

    /**
     * @var string
     */
    private $to;

    public function __construct(string $from, string $to) {
        $this->from = $from;
        $this->to = $to;
    }

    public function getFrom(): string {
        return $this->from;
    }

    public function getTo(): string {
        return $this->to;
    }

    public function toDisplayString(): string {
        return $this->from . ' => ' . $this->to;
    }
}
