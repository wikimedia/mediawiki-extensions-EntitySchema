<?php

namespace Wikibase\Schema\UseCases;

use MediaWiki\MediaWikiServices;
use RequestContext;
use Wikibase\Schema\MediaWiki\RevisionSchemaRepository;
use Wikibase\Schema\UseCases\CreateSchema\CreateSchemaUseCase;

/**
 * @license GPL-2.0-or-later
 */
class UseCaseFactory {

	public static function newCreateSchemaUseCase(): CreateSchemaUseCase {
		return new CreateSchemaUseCase(
			new RevisionSchemaRepository(
				MediaWikiServices::getInstance()->getDBLoadBalancer(),
				RequestContext::getMain()->getUser()
			)
		);
	}

}
