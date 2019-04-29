<?php

namespace EntitySchema\MediaWiki\Actions;

/**
 * Action to handle a submitted EntitySchema page
 */
class SchemaSubmitAction extends SchemaEditAction {

	public function getName() {
		return 'submit';
	}

}
