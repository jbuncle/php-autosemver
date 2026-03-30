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

    private static function fromRangeAndEntries(RevisionRange $range, DiffEntries $entries): self {
        $report = new self($range, null, $entries);
        return $report;
    }

    public static function fromEntries(string $from, string $to, DiffEntries $entries): self {
        return self::fromRangeAndEntries(new RevisionRange($from, $to), $entries);
    }

    /**
     * @param string[] $unchangedSignatures
     * @param string[] $newSignatures
     * @param string[] $removedSignatures
     */
    public static function fromLegacyDisplays(string $from, string $to, array $unchangedSignatures, array $newSignatures, array $removedSignatures): self {
        return self::fromRangeAndEntries(
            new RevisionRange($from, $to),
            DiffEntries::fromLegacyDisplays($unchangedSignatures, $newSignatures, $removedSignatures)
        );
    }

    /**
     *
     * @var RevisionRange
     */
    private $range;

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
        if ($from instanceof RevisionRange) {
            $this->range = $from;
        } else {
            $this->range = new RevisionRange($from, $to);
        }
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
        return $this->renderer->render($this, $level);
    }

    public function getRange(): RevisionRange {
        return $this->range;
    }

    public function getFrom(): string {
        return $this->range->getFrom();
    }

    public function getTo(): string {
        return $this->range->getTo();
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

    public function getIncrementValue(): VersionIncrement {
        return $this->incrementDecider->decide($this->entries);
    }

    /**
     *
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function getIncrement(): string {
        return $this->getIncrementValue()->toString();
    }

}
