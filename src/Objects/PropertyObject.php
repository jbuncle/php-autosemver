<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\PropertySignature;

/**
 * PropertyObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class PropertyObject
        implements Signatures {

    /**
     *
     * @var \PhpParser\Node\Stmt\Property
     */
    private $propertyObj;

    function __construct(\PhpParser\Node\Stmt\Property $propertyObj) {
        $this->propertyObj = $propertyObj;
    }

    public function getSignatures(): array {
        return array_map(function (LegacySignature $signature): string {
            return $signature->toLegacyString();
        }, $this->getSignatureModels());
    }

    /**
     * @return LegacySignature[]
     */
    public function getSignatureModels(): array {
        if ($this->propertyObj->isPrivate()) {
            // Ignore private properties
            return [];
        }
        $prefix = '';

        if ($this->propertyObj->isProtected()) {
            $prefix = 'protected ';
        }
        if ($this->propertyObj->isStatic()) {
            $prefix .= 'static ';
        }

        $sigs = [];
        foreach ($this->propertyObj->props as $prop) {
            $sigs[] = new PropertySignature((string) $prop->name, $prefix);
        }

        return $sigs;
    }

}
