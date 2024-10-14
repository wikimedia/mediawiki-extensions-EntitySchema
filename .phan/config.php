<?php

declare( strict_types = 1 );

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/Wikibase/client',
		'../../extensions/Wikibase/data-access',
		'../../extensions/Wikibase/lib',
		'../../extensions/Wikibase/repo',
		'../../extensions/Wikibase/view',
		'../../extensions/CirrusSearch',
		'../../extensions/Elastica',
	]
);
$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Wikibase/client',
		'../../extensions/Wikibase/data-access',
		'../../extensions/Wikibase/lib',
		'../../extensions/Wikibase/repo',
		'../../extensions/Wikibase/view',
		'../../extensions/CirrusSearch',
		'../../extensions/Elastica',
	]
);
return $cfg;
