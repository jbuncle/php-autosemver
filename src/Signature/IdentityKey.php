<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Signature;

interface IdentityKey {

    public function toIdentityKey(): string;

    public function equals(IdentityKey $other): bool;
}
