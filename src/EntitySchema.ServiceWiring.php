<?php
declare( strict_types = 1 );

use EntitySchema\DataAccess\SqlIdGenerator;
use EntitySchema\Domain\Storage\IdGenerator;
use MediaWiki\MediaWikiServices;

/** @phpcs-require-sorted-array */
return [
	'EntitySchema.IdGenerator' => static function ( MediaWikiServices $services ): IdGenerator {
		return new SqlIdGenerator(
			$services->getDBLoadBalancer(),
			'entityschema_id_counter',
			$services->getMainConfig()->get( 'EntitySchemaSkippedIDs' )
		);
	},
];
