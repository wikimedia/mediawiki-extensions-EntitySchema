<?php

/**
 * Minimal set of classes necessary to fulfill needs of parts of EntitySchema
 * relying on the SyntaxHighlight extension.
 */

namespace MediaWiki\SyntaxHighlight;

class SyntaxHighlight {

	public static function highlight(
		string $code,
		?string $lang = null,
		array $args = [],
		?Parser $parser = null
	) {
	}

}
