<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffSection {

    /**
     * @var SignatureBucket[]
     */
    private $buckets;

    /**
     * @param SignatureBucket[] $buckets
     */
    public function __construct(array $buckets) {
        $this->buckets = $buckets;
    }

    /**
     * @return SignatureBucket[]
     */
    public function getBuckets(): array {
        return $this->buckets;
    }

    /**
     * @return string[]
     */
    public function getDisplays(): array {
        $displays = [];
        foreach ($this->buckets as $bucket) {
            $displays = array_merge($displays, $bucket->getDisplays());
        }

        return $displays;
    }

    public function isEmpty(): bool {
        return empty($this->getDisplays());
    }
}
