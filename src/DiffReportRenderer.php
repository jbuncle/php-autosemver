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
    public function render(DiffReport $report, int $level): string {
        $str = '';
        if ($level >= 1) {
            $str .= "Comparing " . $report->getRange()->toDisplayString() . "\n";
        }
        if ($level >= 2) {
            $str .= "Unchanged:\n";
            foreach ($report->getUnchangedSection()->getDisplays() as $unchangedSignature) {
                $str .= "\t$unchangedSignature\n";
            }
        }
        if ($level >= 1) {
            $str .= "New:\n";
            foreach ($report->getNewSection()->getDisplays() as $newSignature) {
                $str .= "\t$newSignature\n";
            }

            $str .= "Removed:\n";
            foreach ($report->getRemovedSection()->getDisplays() as $removedSignature) {
                $str .= "\t$removedSignature\n";
            }
        }

        return $str . $report->getIncrementValue()->toString();
    }
}
