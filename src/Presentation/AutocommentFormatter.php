<?php

declare( strict_types = 1 );

namespace EntitySchema\Presentation;

use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use RequestContext;

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
	public function formatAutocomment( bool $pre, string $auto, bool $post ): ?string {
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

	private function parseAutocomment( string $auto ): ?string {
		$commentParts = explode( ':', $auto, 2 );
		$context = RequestContext::getMain();

		switch ( $commentParts[0] ) {
			case MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA:
				$comment = wfMessage( 'entityschema-summary-newschema-nolabel' );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT:
				$comment = wfMessage( 'entityschema-summary-update-schema-text' );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_NAMEBADGE:
				$languageName = MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'entityschema-summary-update-schema-namebadge' )
					->params( $languageName );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_LABEL:
				$languageName = MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'entityschema-summary-update-schema-label' )
					->params( $languageName );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_DESCRIPTION:
				$languageName = MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'entityschema-summary-update-schema-description' )
					->params( $languageName );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_ALIASES:
				$languageName = MediaWikiServices::getInstance()->getLanguageNameUtils()->getLanguageName(
					$commentParts[1],
					$context->getLanguage()->getCode()
				);
				$comment = wfMessage( 'entityschema-summary-update-schema-aliases' )
					->params( $languageName );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE:
				[ $revId, $username ] = explode( ':', $commentParts[1], 2 );
				$user = User::newFromName( $username ) ?: $username;
				$comment = wfMessage( 'entityschema-summary-restore-autocomment' )
					->params( $revId, $user );
				break;
			case MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO:
				[ $revId, $username ] = explode( ':', $commentParts[1], 2 );
				$user = User::newFromName( $username ) ?: $username;
				$comment = wfMessage( 'entityschema-summary-undo-autocomment' )
					->params( $revId, $user );
				break;
			default:
				return null;
		}

		return $comment->escaped();
	}

}
