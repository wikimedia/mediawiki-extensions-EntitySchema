<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Context\IContextSource;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\User\UserIdentity;
use Wikibase\Repo\TempUserStatus;
use Wikimedia\Assert\Assert;

/**
 * A Status representing the result of a {@link MediaWikiPageUpdaterFactory}:
 * the PageUpdater, and possibly any temporary account that was created.
 *
 * @inherits TempUserStatus<array{savedTempUser:?UserIdentity,context:IContextSource,pageUpdater:PageUpdater}>
 * @license GPL-2.0-or-later
 */
class PageUpdaterStatus extends TempUserStatus {

	public static function newUpdater(
		PageUpdater $pageUpdater,
		?UserIdentity $savedTempUser,
		IContextSource $context
	): self {
		return self::newTempUserStatus( [
			'pageUpdater' => $pageUpdater,
		], $savedTempUser, $context );
	}

	/**
	 * The newly created PageUpdater.
	 * Only meaningful if the status is {@link self::isOK() OK}.
	 */
	public function getPageUpdater(): PageUpdater {
		Assert::precondition( $this->isOK(), '$this->isOK()' );
		return $this->getValue()['pageUpdater'];
	}

}
