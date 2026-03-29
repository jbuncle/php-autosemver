<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver\Objects;

use AutomaticSemver\Signature\LegacySignature;

interface SignatureModelProvider extends Signatures {

    /**
     * @return LegacySignature[]
     */
    public function getSignatureModels(): array;
}
