<?php
declare( strict_types = 1 );

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\DataAccess\SqlIdGenerator;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\Presentation\AutocommentFormatter;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [
	'EntitySchema.AutocommentFormatter' => static function ( MediaWikiServices $services ): AutocommentFormatter {
		return new AutocommentFormatter();
	},
	'EntitySchema.EntitySchemaExistsValidator' => static function (
		MediaWikiServices $services
	): EntitySchemaExistsValidator {
		return new EntitySchemaExistsValidator( $services->getTitleFactory() );
	},

	'EntitySchema.IdGenerator' => static function ( MediaWikiServices $services ): IdGenerator {
		return new SqlIdGenerator(
			$services->getDBLoadBalancer(),
			'entityschema_id_counter',
			$services->getMainConfig()->get( 'EntitySchemaSkippedIDs' )
		);
	},
	'EntitySchema.LabelLookup' => static function ( MediaWikiServices $services ): LabelLookup {
		return new LabelLookup(
			$services->getWikiPageFactory(),
			WikibaseRepo::getLanguageFallbackChainFactory( $services )
		);
	},
];
