<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use MediaWiki\Actions\FormlessAction;

/**
 * Handles the view action for EntitySchemas.
 * @license GPL-2.0-or-later
 */
class ViewEntitySchemaAction extends FormlessAction {

	/** @inheritDoc */
	public function getName() {
		return 'view';
	}

	/** @inheritDoc */
	public function onView() {
		return null;
	}

	/** @inheritDoc */
	public function needsReadRights() {
		return false;
	}

	/** @inheritDoc */
	public function show() {
		if ( $this->getOutput()->checkLastModified(
			$this->getWikiPage()->getTouched()
		) ) {
			// Client cache fresh and headers sent, nothing more to do.
			return null;
		}

		$this->getArticle()->view();

		$meta = $this->getOutput()->getProperty( 'entityschema-meta-tags' );
		if ( $meta !== null ) {
			$this->getOutput()->setHTMLTitle( $meta['title'] );
		}
	}

}
