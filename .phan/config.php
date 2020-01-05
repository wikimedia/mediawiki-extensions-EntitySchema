<?php

$upstreamConfig = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// These are too spammy for now. TODO enable
$upstreamConfig['null_casts_as_any_type'] = true;
$upstreamConfig['scalar_implicit_cast'] = true;

return $upstreamConfig;
