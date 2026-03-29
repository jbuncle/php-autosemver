<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\Signature\IdentityKey;
use AutomaticSemver\Signature\LegacySignature;

class SignatureBuckets {

    /**
     * @param LegacySignature[] $signatures
     */
    public static function fromSignatures(array $signatures): self {
        $buckets = [];
        foreach ($signatures as $signature) {
            $matchedBucket = (new self($buckets))->findMatching($signature);
            if ($matchedBucket === null) {
                $buckets[] = new SignatureBucket($signature, [$signature->toLegacyString()]);
                continue;
            }

            $matchedBucket->addDisplay($signature->toLegacyString());
        }

        return new self($buckets);
    }

    /**
     * @var SignatureBucket[]
     */
    private $buckets;

    /**
     * @param SignatureBucket[] $buckets
     */
    public function __construct(array $buckets) {
        $this->buckets = $buckets;
    }

    /**
     * @return SignatureBucket[]
     */
    public function all(): array {
        return $this->buckets;
    }

    public function findMatching(IdentityKey $identity): ?SignatureBucket {
        foreach ($this->buckets as $bucket) {
            if ($bucket->matches($identity)) {
                return $bucket;
            }
        }

        return null;
    }
}
