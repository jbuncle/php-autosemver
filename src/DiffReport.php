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
     *
     * @var IncrementDecider
     */
    private $incrementDecider;

    /**
     *
     * @var DiffReportRenderer
     */
    private $renderer;

    /**
     * @param string[]|DiffEntries $unchangedSignatures
     * @param string[] $newSignatures
     * @param string[] $removedSignatures
     */
    public function __construct($from, $to, $unchangedSignatures, $newSignatures = [], $removedSignatures = []) {
        $this->from = $from;
        $this->to = $to;
        $this->incrementDecider = new IncrementDecider();
        $this->renderer = new DiffReportRenderer($this->incrementDecider);
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
        return $this->renderer->render($this->from, $this->to, $this->entries, $level);
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
        return $this->incrementDecider->decide($this->entries);
    }

}
