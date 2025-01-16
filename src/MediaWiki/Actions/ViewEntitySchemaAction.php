<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use FormlessAction;

/**
 * Handles the view action for EntitySchemas.
 * @license GPL-2.0-or-later
 */
class ViewEntitySchemaAction extends FormlessAction {

	public function getName() {
		return 'view';
	}

	public function onView() {
		return null;
	}

	public function needsReadRights() {
		return false;
	}

	public function show() {
		if ( $this->getOutput()->checkLastModified(
			$this->getWikiPage()->getTouched()
		) ) {
			// Client cache fresh and headers sent, nothing more to do.
			return null;
		}

		$this->getArticle()->view();

		$meta = $this->getOutput()->getProperty( 'entityschema-meta-tags' );
		$this->getOutput()->setHTMLTitle( $meta['title'] );
	}

}
