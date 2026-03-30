<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class VersionIncrement {

    private const MAJOR = 'MAJOR';
    private const MINOR = 'MINOR';
    private const PATCH = 'PATCH';

    /**
     * @var string
     */
    private $value;

    private function __construct(string $value) {
        $this->value = $value;
    }

    public static function major(): self {
        return new self(self::MAJOR);
    }

    public static function minor(): self {
        return new self(self::MINOR);
    }

    public static function patch(): self {
        return new self(self::PATCH);
    }

    /**
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function toString(): string {
        return $this->value;
    }

    public function equals(VersionIncrement $other): bool {
        return $this->value === $other->value;
    }

    public function __toString(): string {
        return $this->toString();
    }
}
