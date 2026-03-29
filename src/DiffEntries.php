<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffEntries {

    /**
     * @param string[] $unchanged
     * @param string[] $new
     * @param string[] $removed
     */
    public static function fromLegacyDisplays(array $unchanged, array $new, array $removed): self {
        return new self(
            [new SignatureBucket(new ReportIdentity('unchanged'), $unchanged)],
            [new SignatureBucket(new ReportIdentity('new'), $new)],
            [new SignatureBucket(new ReportIdentity('removed'), $removed)]
        );
    }

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
     * @return string[]
     */
    public function getUnchangedDisplays(): array {
        return $this->flattenDisplays($this->getUnchanged());
    }

    /**
     * @return string[]
     */
    public function getNewDisplays(): array {
        return $this->flattenDisplays($this->getNew());
    }

    /**
     * @return string[]
     */
    public function getRemovedDisplays(): array {
        return $this->flattenDisplays($this->getRemoved());
    }

    public function hasNew(): bool {
        return !empty($this->getNewDisplays());
    }

    public function hasRemoved(): bool {
        return !empty($this->getRemovedDisplays());
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
