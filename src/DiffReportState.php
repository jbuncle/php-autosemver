<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffReportState {

    /**
     * @var RevisionRange
     */
    private $range;

    /**
     * @var DiffEntries
     */
    private $entries;

    /**
     * @var VersionIncrement
     */
    private $increment;

    public function __construct(RevisionRange $range, DiffEntries $entries, VersionIncrement $increment) {
        $this->range = $range;
        $this->entries = $entries;
        $this->increment = $increment;
    }

    public function getRange(): RevisionRange {
        return $this->range;
    }

    public function getEntries(): DiffEntries {
        return $this->entries;
    }

    public function getUnchangedSection(): DiffSection {
        return $this->entries->getUnchanged();
    }

    public function getNewSection(): DiffSection {
        return $this->entries->getNew();
    }

    public function getRemovedSection(): DiffSection {
        return $this->entries->getRemoved();
    }

    public function getIncrement(): VersionIncrement {
        return $this->increment;
    }
}
