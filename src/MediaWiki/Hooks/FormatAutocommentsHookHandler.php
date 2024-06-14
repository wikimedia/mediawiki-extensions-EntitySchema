<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\Presentation\AutocommentFormatter;
use MediaWiki\Hook\FormatAutocommentsHook;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 */
class FormatAutocommentsHookHandler implements FormatAutocommentsHook {
	private AutocommentFormatter $autocommentFormatter;
	private bool $entitySchemaIsRepo;

	public function __construct(
		AutocommentFormatter $autocommentFormatter,
		bool $entitySchemaIsRepo
	) {
		$this->autocommentFormatter = $autocommentFormatter;
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onFormatAutocomments( &$comment, $pre, $auto, $post, $title, $local, $wikiId ) {
		if ( !$this->entitySchemaIsRepo ) {
			return null;
		}

		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgTitle
		global $wgTitle;
		if ( !( $title instanceof Title ) ) {
			$title = $wgTitle;
		}

		if ( !( $title instanceof Title ) ) {
			return null;
		}

		if ( $title->getNamespace() !== NS_ENTITYSCHEMA_JSON ) {
			return null;
		}

		$formattedComment = $this->autocommentFormatter->formatAutocomment( $pre, $auto, $post );
		if ( $formattedComment !== null ) {
			$comment = $formattedComment;
			return false;
		}

		return null;
	}
}
