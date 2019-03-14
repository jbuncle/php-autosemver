<?php

/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

/**
 * Description of PropertyObject
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
        if ($this->propertyObj->isPrivate()) {
            // Ignore private properties
            return [];
        }
        $prefix = '';

        $wrap = false;
        if ($this->propertyObj->isProtected()) {
            $prefix = 'protected ';
        }
        if ($this->propertyObj->isStatic()) {
            $prefix .= 'static ';
        }
        $prefix .= '$';

        $sigs = [];
        foreach ($this->propertyObj->props as $prop) {
            $sig = $prefix . ((string) $prop->name);
            if ($wrap) {
                $sigs[] = '{' . $sig . '}';
            } else {
                $sigs[] = $sig;
            }
        }

        return $sigs;
    }

}
