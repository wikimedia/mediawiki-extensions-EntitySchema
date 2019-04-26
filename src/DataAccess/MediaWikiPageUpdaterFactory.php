<?php

namespace EntitySchema\DataAccess;

use MediaWiki\Storage\PageUpdater;
use Title;
use User;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class MediaWikiPageUpdaterFactory {

	private $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	public function getPageUpdater( $pageTitleString ): PageUpdater {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageTitleString );
		$wikipage = WikiPage::factory( $title );
		return $wikipage->newPageUpdater( $this->user );
	}

}
