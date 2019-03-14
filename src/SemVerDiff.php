<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk)  - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\GitSearch\GitSearch;

/**
 * SemVerDiff
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SemVerDiff {

    private $root;

    private $paths;

    public function __construct(string $root, array $paths) {
        $this->root = $root;
        $this->paths = $paths;
    }

    public function diff(string $startRevision, string $endRevision): string {
        $signatureSearch = new SignatureSearch();
        $gitSearch = new GitSearch();

        $startFiles = $gitSearch->findFiles($this->root, $this->paths, $startRevision);
        $prevSignatures = $signatureSearch->getSignatures($startFiles);

        $endFiles = $gitSearch->findFiles($this->root, $this->paths, $endRevision);
        $currentSignatures = $signatureSearch->getSignatures($endFiles);

        $unchangedSignatures = array_intersect($currentSignatures, $prevSignatures);
        $newSignatures = array_diff($currentSignatures, $prevSignatures);
        $removedSignatures = array_diff($prevSignatures, $currentSignatures);

        echo "Unchanged:\n";
        foreach ($unchangedSignatures as $unchangedSignature) {
            echo "\t\\$unchangedSignature\n";
        }

        echo "New:\n";
        foreach ($newSignatures as $newSignature) {
            echo "\t\\$newSignature\n";
        }

        echo "Removed:\n";
        foreach ($removedSignatures as $removedSignature) {
            echo "\t\\$removedSignature\n";
        }

        if (!empty($removedSignatures)) {
            return "MAJOR";
        } else if (!empty($newSignatures)) {
            return "MINOR";
        } else {
            return "PATCH";
        }
    }

}
