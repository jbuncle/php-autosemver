<?php

require_once __DIR__ . '/../vendor/autoload.php';
/*
 * Copyright (C) 2019 James Buncle (https://www.jbuncle.co.uk) - All Rights Reserved
 */

use AutomaticSemver\SemVerDiff;

$root = '/home/jbuncle/Projects/wordpress/wpdocker/wp-theme';
$paths = ['src'];
$revision = '9943b2b7098362ca56402af10ba997b5762918f7';

$semVerDiff = new SemVerDiff($root, $paths);
echo $semVerDiff->diff($revision, 'HEAD');
