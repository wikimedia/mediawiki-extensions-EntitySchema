<?php
declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaServices {
	public static function getEntitySchemaExistsValidator(
		ContainerInterface $services = null
	): EntitySchemaExistsValidator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.EntitySchemaExistsValidator' );
	}

	public static function getIdGenerator( ContainerInterface $services = null ): IdGenerator {
		return ( $services ?: MediaWikiServices::getInstance() )
			->get( 'EntitySchema.IdGenerator' );
	}
}
