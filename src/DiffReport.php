<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

/**
 * DiffReport
 *
 * @author jbuncle
 */
class DiffReport {

    /**
     *
     * @var string
     */
    private $from;

    /**
     *
     * @var string
     */
    private $to;

    /**
     *
     * @var array<string>
     */
    private $unchangedSignatures;

    /**
     *
     * @var array<string>
     */
    private $newSignatures;

    /**
     *
     * @var array<string>
     */
    private $removedSignatures;

    public function __construct($from, $to, $unchangedSignatures, $newSignatures, $removedSignatures) {
        $this->from = $from;
        $this->to = $to;
        $this->unchangedSignatures = $unchangedSignatures;
        $this->newSignatures = $newSignatures;
        $this->removedSignatures = $removedSignatures;
    }

    /**
     * 
     * @param 0|1|2 $level
     * @return string
     */
    public function toString(int $level): string {
        $str = "";
        if ($level >= 1) {
            $str .= "Comparing $this->from => $this->to\n";
        }
        if ($level >= 2) {
            $str .= "Unchanged:\n";
            foreach ($this->unchangedSignatures as $unchangedSignature) {
                $str .= "\t$unchangedSignature\n";
            }
        }
        if ($level >= 1) {

            $str .= "New:\n";
            foreach ($this->newSignatures as $newSignature) {
                $str .= "\t$newSignature\n";
            }

            $str .= "Removed:\n";
            foreach ($this->removedSignatures as $removedSignature) {
                $str .= "\t$removedSignature\n";
            }
        }

        $str .= $this->getIncrement();
        return $str;
    }

    /**
     *
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function getIncrement(): string {
        if (!empty($this->removedSignatures)) {
            return "MAJOR";
        } else if (!empty($this->newSignatures)) {
            return "MINOR";
        } else {
            return "PATCH";
        }
    }

}
