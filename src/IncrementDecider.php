<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class IncrementDecider {

    /**
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function decide(DiffEntries $entries): string {
        if ($entries->hasRemoved()) {
            return "MAJOR";
        }

        if ($entries->hasNew()) {
            return "MINOR";
        }

        return "PATCH";
    }
}
