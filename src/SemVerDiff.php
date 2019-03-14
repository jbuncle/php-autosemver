<?php

/*
 * Copyright (C) 2019 CyberPear (https://www.cyberpear.co.uk) - All Rights Reserved
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

        $startFiles = $gitSearch->findFiles($this->root, $this->paths,
                $startRevision);
        $prevSignatures = $signatureSearch->getSignatures($startFiles);

        $endFiles = $gitSearch->findFiles($this->root, $this->paths,
                $endRevision);
        $currentSignatures = $signatureSearch->getSignatures($endFiles);

        $new = array_diff($currentSignatures, $prevSignatures);

        echo "New:\n";
        foreach ($new as $newSig) {
            echo "\t$newSig\n";
        }
        $removed = array_diff($prevSignatures, $currentSignatures);

        echo "Removed:\n";
        foreach ($removed as $newSig) {
            echo "\t$newSig\n";
        }

        if (!empty($removed)) {
            return "MAJOR";
        } else if (!empty($new)) {
            return "MINOR";
        } else {
            return "PATCH";
        }
    }

}
