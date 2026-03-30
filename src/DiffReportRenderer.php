<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

class DiffReportRenderer {

    /**
     * @param 0|1|2 $level
     */
    public function render(DiffReport $report, int $level): string {
        return $this->renderState($report->getState(), $level);
    }

    /**
     * @param 0|1|2 $level
     */
    public function renderState(DiffReportState $state, int $level): string {
        $str = '';
        if ($level >= 1) {
            $str .= "Comparing " . $state->getRange()->toDisplayString() . "
";
        }
        if ($level >= 2) {
            $str .= "Unchanged:
";
            foreach ($state->getUnchangedSection()->getDisplays() as $unchangedSignature) {
                $str .= "	$unchangedSignature
";
            }
        }
        if ($level >= 1) {
            $str .= "New:
";
            foreach ($state->getNewSection()->getDisplays() as $newSignature) {
                $str .= "	$newSignature
";
            }

            $str .= "Removed:
";
            foreach ($state->getRemovedSection()->getDisplays() as $removedSignature) {
                $str .= "	$removedSignature
";
            }
        }

        return $str . $state->getIncrement()->toString();
    }
}
