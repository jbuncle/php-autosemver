<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class IncrementDecider {

    public function decide(DiffEntries $entries): VersionIncrement {
        if ($entries->hasRemoved()) {
            return VersionIncrement::major();
        }

        if ($entries->hasNew()) {
            return VersionIncrement::minor();
        }

        return VersionIncrement::patch();
    }
}
