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
     * @var DiffEntries
     */
    private $entries;

    /**
     * @param string[]|DiffEntries $unchangedSignatures
     * @param string[] $newSignatures
     * @param string[] $removedSignatures
     */
    public function __construct($from, $to, $unchangedSignatures, $newSignatures = [], $removedSignatures = []) {
        $this->from = $from;
        $this->to = $to;
        if ($unchangedSignatures instanceof DiffEntries) {
            $this->entries = $unchangedSignatures;
            return;
        }

        $this->entries = DiffEntries::fromLegacyDisplays($unchangedSignatures, $newSignatures, $removedSignatures);
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
            foreach ($this->getUnchangedSignatures() as $unchangedSignature) {
                $str .= "\t$unchangedSignature\n";
            }
        }
        if ($level >= 1) {

            $str .= "New:\n";
            foreach ($this->getNewSignatures() as $newSignature) {
                $str .= "\t$newSignature\n";
            }

            $str .= "Removed:\n";
            foreach ($this->getRemovedSignatures() as $removedSignature) {
                $str .= "\t$removedSignature\n";
            }
        }

        $str .= $this->getIncrement();
        return $str;
    }

    /**
     * @return string[]
     */
    public function getUnchangedSignatures(): array {
        return $this->entries->getUnchangedDisplays();
    }

    /**
     * @return string[]
     */
    public function getNewSignatures(): array {
        return $this->entries->getNewDisplays();
    }

    /**
     * @return string[]
     */
    public function getRemovedSignatures(): array {
        return $this->entries->getRemovedDisplays();
    }

    /**
     *
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function getIncrement(): string {
        if ($this->entries->hasRemoved()) {
            return "MAJOR";
        } else if ($this->entries->hasNew()) {
            return "MINOR";
        } else {
            return "PATCH";
        }
    }

}
