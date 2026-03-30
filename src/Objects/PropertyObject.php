<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;
use AutomaticSemver\Signature\PropertySignature;
use AutomaticSemver\Signature\TypeReference;
use AutomaticSemver\TypeLookup;

/**
 * PropertyObject
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
class PropertyObject
        implements SignatureModelProvider {

    /**
     *
     * @var \PhpParser\Node\Stmt\Property
     */
    private $propertyObj;

    /**
     * @var TypeLookup|null
     */
    private $typeLookup;

    public function __construct(\PhpParser\Node\Stmt\Property $propertyObj, ?TypeLookup $typeLookup = null) {
        $this->propertyObj = $propertyObj;
        $this->typeLookup = $typeLookup;
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

        $visibility = $this->propertyObj->isProtected() ? 'protected' : 'public';
        $isStatic = $this->propertyObj->isStatic();
        $type = $this->getPropertyType();

        $sigs = [];
        foreach ($this->propertyObj->props as $prop) {
            $sigs[] = new PropertySignature((string) $prop->name, $visibility, $isStatic, $type);
        }

        return $sigs;
    }

    private function getPropertyType(): ?TypeReference {
        if ($this->propertyObj->type === null || $this->typeLookup === null) {
            return null;
        }

        return new TypeReference($this->typeLookup->getAbsoluteType($this->propertyObj->type));
    }
}
