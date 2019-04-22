<?php

namespace Wikibase\Schema\Presentation;

use RequestContext;
use User;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaInserter;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaUpdater;
use Language;

/**
 * @license GPL-2.0-or-later
 */
class AutocommentFormatter {

	/**
	 * @param bool   $pre  Whether any text appears in the summary before this autocomment.
	 *                     If true, we insert the autocomment-prefix before the autocomment
	 *                     (outside the two <span>s) to separate it from that.
	 * @param string $auto The autocomment content (without the surrounding comment marks)
	 * @param bool   $post Whether any text appears in the summary after this autocomment.
	 *                     If true, we append the colon-separator after the autocomment
	 *                     (still inside the two <span>s) to separate it from that.
	 *
	 * @return string|null
	 */
	public function formatAutocomment( $pre, $auto, $post ) {
		$comment = $this->parseAutocomment( $auto );
		if ( $comment === null ) {
			return null;
		}

		if ( $post ) {
			$comment .= wfMessage( 'colon-separator' )->escaped();
		}

		$comment = '<span dir="auto"><span class="autocomment">' . $comment . '</span></span>';

		if ( $pre ) {
			$comment = wfMessage( 'autocomment-prefix' )->escaped() . $comment;
		}

		return $comment;
	}

	private function parseAutocomment( $auto ) {
		$commentParts = explode( ':', $auto, 2 );
		$context = RequestContext::getMain();

		switch ( $commentParts[0] ) {
			case MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA:
				$comment = wfMessage( 'wikibaseschema-summary-newschema-nolabel' );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT:
				$comment = wfMessage( 'wikibaseschema-summary-update-schema-text' );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE:
				$languageName = Language::fetchLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'wikibaseschema-summary-update-schema-namebadge' )
					->params( $languageName );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_LABEL:
				$languageName = Language::fetchLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'wikibaseschema-summary-update-schema-label' )
					->params( $languageName );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION:
				$languageName = Language::fetchLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'wikibaseschema-summary-update-schema-description' )
					->params( $languageName );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES:
				$languageName = Language::fetchLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'wikibaseschema-summary-update-schema-aliases' )
					->params( $languageName );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_RESTORE:
				list( $revId, $username ) = explode( ':', $commentParts[1], 2 );
				$user = User::newFromName( $username ) ?: $username;
				$comment = wfMessage( 'wikibaseschema-summary-restore-autocomment' )
					->params( $revId, $user );
				break;
			case MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UNDO:
				list( $revId, $username ) = explode( ':', $commentParts[1], 2 );
				$user = User::newFromName( $username ) ?: $username;
				$comment = wfMessage( 'wikibaseschema-summary-undo-autocomment' )
					->params( $revId, $user );
				break;
			default:
				return null;
		}

		return $comment->escaped();
	}

}
