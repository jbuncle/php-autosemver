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
     * @var DiffSection
     */
    private $unchanged;

    /**
     * @var DiffSection
     */
    private $new;

    /**
     * @var DiffSection
     */
    private $removed;

    /**
     * @param SignatureBucket[] $unchanged
     * @param SignatureBucket[] $new
     * @param SignatureBucket[] $removed
     */
    public function __construct(array $unchanged, array $new, array $removed) {
        $this->unchanged = new DiffSection($unchanged);
        $this->new = new DiffSection($new);
        $this->removed = new DiffSection($removed);
    }

    /**
     * @return DiffSection
     */
    public function getUnchanged(): DiffSection {
        return $this->unchanged;
    }

    /**
     * @return DiffSection
     */
    public function getNew(): DiffSection {
        return $this->new;
    }

    /**
     * @return DiffSection
     */
    public function getRemoved(): DiffSection {
        return $this->removed;
    }

    /**
     * @return string[]
     */
    public function getUnchangedDisplays(): array {
        return $this->getUnchanged()->getDisplays();
    }

    /**
     * @return string[]
     */
    public function getNewDisplays(): array {
        return $this->getNew()->getDisplays();
    }

    /**
     * @return string[]
     */
    public function getRemovedDisplays(): array {
        return $this->getRemoved()->getDisplays();
    }

    public function hasNew(): bool {
        return !$this->getNew()->isEmpty();
    }

    public function hasRemoved(): bool {
        return !$this->getRemoved()->isEmpty();
    }

}
