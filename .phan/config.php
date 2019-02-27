<?php

$upstreamConfig = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$upstreamConfig['suppress_issue_types'][] = 'PhanUndeclaredConstant';
$upstreamConfig['suppress_issue_types'][] = 'PhanUndeclaredMethod';

$upstreamConfig['directory_list'] = array_diff(
	$upstreamConfig['directory_list'],
	[ 'includes/', 'maintenance/', 'tests/phan/stubs/' ]
);

return $upstreamConfig;
