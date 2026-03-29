<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

interface LegacySignature {

    public function toLegacyString(): string;

    public function __toString(): string;
}
