<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffReportStateFactory {

    /**
     * @var IncrementDecider
     */
    private $incrementDecider;

    public function __construct(?IncrementDecider $incrementDecider = null) {
        $this->incrementDecider = $incrementDecider ?? new IncrementDecider();
    }

    public function create(RevisionRange $range, DiffEntries $entries): DiffReportState {
        return new DiffReportState($range, $entries, $this->incrementDecider->decide($entries));
    }
}
