<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\Presentation\AutocommentFormatter;
use MediaWiki\Hook\FormatAutocommentsHook;
use Title;

/**
 * @license GPL-2.0-or-later
 */
class FormatAutocommentsHookHandler implements FormatAutocommentsHook {
	private AutocommentFormatter $autocommentFormatter;

	public function __construct( AutocommentFormatter $autocommentFormatter ) {
		$this->autocommentFormatter = $autocommentFormatter;
	}

	/**
	 * @inheritDoc
	 */
	public function onFormatAutocomments( &$comment, $pre, $auto, $post, $title, $local, $wikiId ) {
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
