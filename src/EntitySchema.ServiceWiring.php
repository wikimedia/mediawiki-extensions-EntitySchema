<?php
declare( strict_types = 1 );

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\SchemaDataResolvingLabelLookup;
use EntitySchema\DataAccess\SqlIdGenerator;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\MediaWiki\HookRunner;
use EntitySchema\Presentation\AutocommentFormatter;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\MediaWikiServices;
use Wikibase\Repo\WikibaseRepo;

/** @phpcs-require-sorted-array */
return [
	'EntitySchema.AutocommentFormatter' => static function ( MediaWikiServices $services ): AutocommentFormatter {
		return new AutocommentFormatter();
	},

	'EntitySchema.DescriptionLookup' => static function ( MediaWikiServices $services ): ?DescriptionLookup {
		if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
			return null;
		}
		return new DescriptionLookup(
			EntitySchemaServices::getFullViewSchemaDataLookup( $services ),
			WikibaseRepo::getLanguageFallbackChainFactory( $services )
		);
	},

	'EntitySchema.EntitySchemaExistsValidator' => static function (
		MediaWikiServices $services
	): ?EntitySchemaExistsValidator {
		if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
			return null;
		}
		return new EntitySchemaExistsValidator( $services->getTitleFactory() );
	},

	'EntitySchema.EntitySchemaIsRepo' => static function ( MediaWikiServices $services ): bool {
		return $services->getMainConfig()->get( 'EntitySchemaIsRepo' );
	},

	'EntitySchema.EntitySchemaSearchHelperFactory' => static function (
		MediaWikiServices $services
	): ?EntitySchemaSearchHelperFactory {
		if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
			return null;
		}
		return new EntitySchemaSearchHelperFactory(
			$services->getConfigFactory(),
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			WikibaseRepo::getLanguageFallbackChainFactory( $services ),
			WikibaseRepo::getLocalEntitySource( $services )->getConceptBaseUri(),
			EntitySchemaServices::getDescriptionLookup( $services ),
			EntitySchemaServices::getSchemaDataResolvingLabelLookup( $services )
		);
	},

	'EntitySchema.FullViewSchemaDataLookup' => static function (
		MediaWikiServices $services
	): FullViewSchemaDataLookup {
		return new FullViewSchemaDataLookup(
			$services->getTitleFactory(),
			$services->getWikiPageFactory()
		);
	},

	'EntitySchema.HookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner(
			$services->getHookContainer()
		);
	},

	'EntitySchema.IdGenerator' => static function ( MediaWikiServices $services ): ?IdGenerator {
		if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
			return null;
		}
		return new SqlIdGenerator(
			$services->getDBLoadBalancer(),
			'entityschema_id_counter',
			$services->getMainConfig()->get( 'EntitySchemaSkippedIDs' )
		);
	},

	'EntitySchema.LabelLookup' => static function ( MediaWikiServices $services ): ?LabelLookup {
		if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
			return null;
		}
		return new LabelLookup(
			WikibaseRepo::getLanguageFallbackChainFactory( $services )
		);
	},

	'EntitySchema.MediaWikiPageUpdaterFactory' => static function (
		MediaWikiServices $services
	): MediaWikiPageUpdaterFactory {
		return new MediaWikiPageUpdaterFactory(
			$services->getPermissionManager(),
			$services->getTempUserCreator(),
			$services->getTitleFactory(),
			$services->getWikiPageFactory()
		);
	},

	'EntitySchema.SchemaDataResolvingLabelLookup' =>
		static function ( MediaWikiServices $services ): ?SchemaDataResolvingLabelLookup {
			if ( !$services->getMainConfig()->get( 'EntitySchemaIsRepo' ) ) {
				return null;
			}
			return new SchemaDataResolvingLabelLookup(
				EntitySchemaServices::getFullViewSchemaDataLookup( $services ),
				EntitySchemaServices::getLabelLookup( $services )
			);
		},

	'EntitySchema.WatchlistUpdater' => static function (
		MediaWikiServices $services
	): WatchlistUpdater {
		return new WatchlistUpdater(
			$services->getUserOptionsLookup(),
			$services->getWatchlistManager()
		);
	},

];
