<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\Signature\LegacySignature;

class SignatureDiffSnapshot {

    /**
     * @param LegacySignature[] $previousSignatures
     * @param LegacySignature[] $currentSignatures
     */
    public static function fromSignatures(array $previousSignatures, array $currentSignatures): self {
        return new self(
            SignatureBuckets::fromSignatures($previousSignatures),
            SignatureBuckets::fromSignatures($currentSignatures)
        );
    }

    /**
     * @var SignatureBuckets
     */
    private $previous;

    /**
     * @var SignatureBuckets
     */
    private $current;

    public function __construct(SignatureBuckets $previous, SignatureBuckets $current) {
        $this->previous = $previous;
        $this->current = $current;
    }

    public function toEntries(): DiffEntries {
        $unchanged = [];
        $new = [];
        foreach ($this->current->all() as $bucket) {
            if ($this->previous->findMatching($bucket->getIdentity()) !== null) {
                $unchanged[] = $bucket;
            } else {
                $new[] = $bucket;
            }
        }

        $removed = [];
        foreach ($this->previous->all() as $bucket) {
            if ($this->current->findMatching($bucket->getIdentity()) === null) {
                $removed[] = $bucket;
            }
        }

        return new DiffEntries($unchanged, $new, $removed);
    }
}
