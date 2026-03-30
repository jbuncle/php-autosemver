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
     * @var DiffReportStateFactory
     */
    private static $stateFactory;

    private static function fromRangeAndEntries(RevisionRange $range, DiffEntries $entries): self {
        $report = new self($range, null, $entries);
        return $report;
    }

    private static function getStateFactory(): DiffReportStateFactory {
        if (self::$stateFactory === null) {
            self::$stateFactory = new DiffReportStateFactory();
        }

        return self::$stateFactory;
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
     * @var DiffReportState
     */
    private $state;

    /**
     * @var DiffReportRenderer
     */
    private $renderer;

    /**
     * @param string[]|DiffEntries $unchangedSignatures
     * @param string[] $newSignatures
     * @param string[] $removedSignatures
     */
    public function __construct($from, $to, $unchangedSignatures, $newSignatures = [], $removedSignatures = []) {
        $range = $from instanceof RevisionRange ? $from : new RevisionRange($from, $to);
        $entries = $unchangedSignatures instanceof DiffEntries
            ? $unchangedSignatures
            : DiffEntries::fromLegacyDisplays($unchangedSignatures, $newSignatures, $removedSignatures);

        $this->state = self::getStateFactory()->create($range, $entries);
        $this->renderer = new DiffReportRenderer();
    }

    public function getState(): DiffReportState {
        return $this->state;
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
        return $this->state->getRange();
    }

    public function getFrom(): string {
        return $this->getRange()->getFrom();
    }

    public function getTo(): string {
        return $this->getRange()->getTo();
    }

    public function getUnchangedSection(): DiffSection {
        return $this->state->getUnchangedSection();
    }

    public function getNewSection(): DiffSection {
        return $this->state->getNewSection();
    }

    public function getRemovedSection(): DiffSection {
        return $this->state->getRemovedSection();
    }

    /**
     * @return string[]
     */
    public function getUnchangedSignatures(): array {
        return $this->getUnchangedSection()->getDisplays();
    }

    /**
     * @return string[]
     */
    public function getNewSignatures(): array {
        return $this->getNewSection()->getDisplays();
    }

    /**
     * @return string[]
     */
    public function getRemovedSignatures(): array {
        return $this->getRemovedSection()->getDisplays();
    }

    public function getIncrementValue(): VersionIncrement {
        return $this->state->getIncrement();
    }

    /**
     *
     * @return "MAJOR"|"MINOR"|"PATCH"
     */
    public function getIncrement(): string {
        return $this->getIncrementValue()->toString();
    }

}
