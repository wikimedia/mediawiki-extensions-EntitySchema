<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Status\Status;
use MediaWiki\Storage\PageUpdater;
use Wikimedia\Assert\Assert;

/**
 * A Status representing the result of a {@link MediaWikiPageUpdaterFactory}.
 *
 * @license GPL-2.0-or-later
 */
class PageUpdaterStatus extends Status {

	public static function newUpdater(
		PageUpdater $pageUpdater
	): self {
		return self::newGood( [
			'pageUpdater' => $pageUpdater,
		] );
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
