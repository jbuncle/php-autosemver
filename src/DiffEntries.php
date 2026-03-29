<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffEntries {

    /**
     * @var SignatureBucket[]
     */
    private $unchanged;

    /**
     * @var SignatureBucket[]
     */
    private $new;

    /**
     * @var SignatureBucket[]
     */
    private $removed;

    /**
     * @param SignatureBucket[] $unchanged
     * @param SignatureBucket[] $new
     * @param SignatureBucket[] $removed
     */
    public function __construct(array $unchanged, array $new, array $removed) {
        $this->unchanged = $unchanged;
        $this->new = $new;
        $this->removed = $removed;
    }

    /**
     * @return SignatureBucket[]
     */
    public function getUnchanged(): array {
        return $this->unchanged;
    }

    /**
     * @return SignatureBucket[]
     */
    public function getNew(): array {
        return $this->new;
    }

    /**
     * @return SignatureBucket[]
     */
    public function getRemoved(): array {
        return $this->removed;
    }

    /**
     * @param SignatureBucket[] $buckets
     * @return string[]
     */
    public function flattenDisplays(array $buckets): array {
        $displays = [];
        foreach ($buckets as $bucket) {
            $displays = array_merge($displays, $bucket->getDisplays());
        }
        return $displays;
    }
}
