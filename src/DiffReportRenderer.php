<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffReportRenderer {

    private $incrementDecider;

    public function __construct(?IncrementDecider $incrementDecider = null) {
        $this->incrementDecider = $incrementDecider ?? new IncrementDecider();
    }

    /**
     * @param 0|1|2 $level
     */
    public function render(string $from, string $to, DiffEntries $entries, int $level): string {
        $str = '';
        if ($level >= 1) {
            $str .= "Comparing $from => $to\n";
        }
        if ($level >= 2) {
            $str .= "Unchanged:\n";
            foreach ($entries->getUnchangedDisplays() as $unchangedSignature) {
                $str .= "\t$unchangedSignature\n";
            }
        }
        if ($level >= 1) {
            $str .= "New:\n";
            foreach ($entries->getNewDisplays() as $newSignature) {
                $str .= "\t$newSignature\n";
            }

            $str .= "Removed:\n";
            foreach ($entries->getRemovedDisplays() as $removedSignature) {
                $str .= "\t$removedSignature\n";
            }
        }

        return $str . $this->incrementDecider->decide($entries);
    }
}
