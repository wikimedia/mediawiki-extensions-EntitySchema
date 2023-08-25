<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

/**
 * Action to handle a submitted EntitySchema page
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaSubmitAction extends EntitySchemaEditAction {

	public function getName(): string {
		return 'submit';
	}

}
