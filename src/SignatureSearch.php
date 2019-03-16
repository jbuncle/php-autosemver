<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

use AutomaticSemver\Objects\RootNamespaceObject;
use Exception;
use PhpParser\ParserFactory;

/**
 * SignatureSearch
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class SignatureSearch {

    public function getSignatures(array $files): array {

        $signatures = [];
        foreach ($files as $file) {
            try {
                $code = $file->getContent();
                $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

                $ast = $parser->parse($code);
                $rootNamespace = new RootNamespaceObject($ast);

                foreach ($rootNamespace->getSignatures() as $signature) {
                    $signatures[] = $signature;
                }
            } catch (Exception $ex) {
                $filepath = $file->getPath();
                throw new Exception("Failed to process '$filepath'", 0, $ex);
            }
        }
        return $signatures;
    }

}
