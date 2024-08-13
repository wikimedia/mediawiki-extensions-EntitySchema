<?php
declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use EntitySchema\DataAccess\DescriptionLookup;
use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\Presentation\AutocommentFormatter;
use EntitySchema\Wikibase\FeatureConfiguration;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaServices {
	public static function getAutocommentFormatter( ContainerInterface $services = null ): AutocommentFormatter {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.AutocommentFormatter' );
	}

	public static function getDescriptionLookup( ContainerInterface $services = null ): DescriptionLookup {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.DescriptionLookup' );
	}

	public static function getEntitySchemaExistsValidator(
		ContainerInterface $services = null
	): EntitySchemaExistsValidator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.EntitySchemaExistsValidator' );
	}

	public static function getEntitySchemaSearchHelperFactory(
		ContainerInterface $services = null
	): EntitySchemaSearchHelperFactory {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.EntitySchemaSearchHelperFactory' );
	}

	public static function getFeatureConfiguration(
		ContainerInterface $services = null
	): FeatureConfiguration {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.FeatureConfiguration' );
	}

	public static function getFullViewSchemaDataLookup(
		ContainerInterface $services = null
	): FullViewSchemaDataLookup {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.FullViewSchemaDataLookup' );
	}

	public static function getIdGenerator( ContainerInterface $services = null ): IdGenerator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.IdGenerator' );
	}

	public static function getLabelLookup( ContainerInterface $services = null ): LabelLookup {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.LabelLookup' );
	}

	public static function getEntitySchemaIsRepo( ContainerInterface $services = null ): bool {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.EntitySchemaIsRepo' );
	}

	public static function getWatchlistUpdater(
		ContainerInterface $services = null
	): WatchlistUpdater {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.WatchlistUpdater' );
	}
}
