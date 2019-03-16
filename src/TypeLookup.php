<?php

/*
 * Copyright (C) 2019 James Buncle (https://jbuncle.co.uk) - All Rights Reserved
 */

namespace AutomaticSemver;

/**
 *
 * @author James Buncle <jbuncle@hotmail.com>
 */
interface TypeLookup {

    public function getSignatures(): array;
}
