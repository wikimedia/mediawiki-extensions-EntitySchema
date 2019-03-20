<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Action;
use Article;
use Content;
use IContextSource;
use JsonContentHandler;
use LogicException;
use MediaWiki\MediaWikiServices;
use Page;
use RequestContext;
use Revision;
use SlotDiffRenderer;
use Wikibase\Schema\DataAccess\SchemaEncoder;
use Wikibase\Schema\MediaWiki\Actions\RestoreSubmitAction;
use Wikibase\Schema\MediaWiki\Actions\RestoreViewAction;
use Wikibase\Schema\MediaWiki\Actions\SchemaEditAction;
use Wikibase\Schema\MediaWiki\Actions\SchemaSubmitAction;
use Wikibase\Schema\MediaWiki\Actions\UndoSubmitAction;
use Wikibase\Schema\MediaWiki\Actions\UndoViewAction;
use Wikibase\Schema\MediaWiki\UndoHandler;
use Wikibase\Schema\Presentation\InputValidator;
use WikiPage;

/**
 * Content handler for the Wikibase Schema content
 */
class WikibaseSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = WikibaseSchemaContent::CONTENT_MODEL_ID ) {
		parent::__construct( $modelId );
	}

	protected function getContentClass() {
		return WikibaseSchemaContent::class;
	}

	protected function getSlotDiffRendererInternal( IContextSource $context ): SlotDiffRenderer {
		return new WikibaseSchemaSlotDiffRenderer( $context );
	}

	public function getActionOverrides() {
		return [
			'edit' => function( Page $page, IContextSource $context = null ) {
				if ( $context === null ) {
					$context = RequestContext::getMain();
				}

				/** @var Article|WikiPage $page */
				// @phan-suppress-next-line PhanUndeclaredMethod
				if ( $page->getRevision() === null ) {
					return Action::factory( 'view', $page, $context );
				}

				$req = $context->getRequest();

				if (
					$req->getCheck( 'undo' )
					|| $req->getCheck( 'undoafter' )
				) {
					return new UndoViewAction(
						$page,
						new WikibaseSchemaSlotDiffRenderer( $context ),
						$context
					);
				}

				if ( $req->getCheck( 'restore' ) ) {
					return new RestoreViewAction(
						$page,
						new WikibaseSchemaSlotDiffRenderer( $context ),
						$context
					);
				}

				// TODo: check redirect?
				// !$page->isRedirect()
				return new SchemaEditAction(
					$page,
					new InputValidator(
						$context,
						MediaWikiServices::getInstance()->getMainConfig()
					),
					$context
				);
			},
			'submit' => function( Page $page, IContextSource $context = null ) {
				if ( $context === null ) {
					$context = RequestContext::getMain();
				}

				$req = $context->getRequest();

				if (
					$req->getCheck( 'undo' )
					|| $req->getCheck( 'undoafter' )
				) {
					return new UndoSubmitAction( $page, $context );
				}

				if ( $req->getCheck( 'restore' ) ) {
					return new RestoreSubmitAction( $page, $context );
				}

				return SchemaSubmitAction::class;
			},
		];
	}

	public function supportsDirectApiEditing() {
		return false;
	}

	/**
	 * Get the Content object that needs to be saved in order to undo all revisions
	 * between $undo and $undoafter. Revisions must belong to the same page,
	 * must exist and must not be deleted.
	 *
	 * @since 1.32 accepts Content objects for all parameters instead of Revision objects.
	 *  Passing Revision objects is deprecated.
	 *
	 * @param Revision|Content $base The current text
	 * @param Revision|Content $undoFrom The content of the revision to undo
	 * @param Revision|Content $undoTo Must be from an earlier revision than $undo
	 * @param bool $undoIsLatest Set true if $undo is from the current revision (since 1.32)
	 *
	 * @return Content|false
	 */
	public function getUndoContent( $base, $undoFrom, $undoTo, $undoIsLatest = false ) {
		$undoToContent = ( $undoTo instanceof Revision ) ? $undoTo->getContent() : $undoTo;
		if ( $undoIsLatest ) {
			return $undoToContent;
		}

		$baseContent = ( $base instanceof Revision ) ? $base->getContent() : $base;
		$undoFromContent = ( $undoFrom instanceof Revision ) ? $undoFrom->getContent() : $undoFrom;

		$undoHandler = new UndoHandler();
		try {
			$schemaId = $undoHandler->validateContentIds( $undoToContent, $undoFromContent, $baseContent );
		} catch ( LogicException $e ) {
			return false;
		}

		$diffStatus = $undoHandler->getDiffFromContents( $undoFromContent, $undoToContent );
		if ( !$diffStatus->isOK() ) {
			return false;
		}

		$patchStatus = $undoHandler->tryPatching( $diffStatus->getValue(), $baseContent );
		if ( !$patchStatus->isOK() ) {
			return false;
		}
		$patchedSchema = $patchStatus->getValue()->data;

		return new WikibaseSchemaContent( SchemaEncoder::getPersistentRepresentation(
			$schemaId,
			$patchedSchema['labels'],
			$patchedSchema['descriptions'],
			$patchedSchema['aliases'],
			$patchedSchema['schemaText']
		) );
	}

	/**
	 * Returns true to indicate that the parser cache can be used for Schemas.
	 *
	 * @note: The html representation of Schemas depends on the user language, so
	 * WikibaseSchemaContent::getParserOutput needs to make sure
	 * ParserOutput::recordOption( 'userlang' ) is called to split the cache by user language.
	 *
	 * @see ContentHandler::isParserCacheSupported
	 *
	 * @return bool Always true in this default implementation.
	 */
	public function isParserCacheSupported() {
		return true;
	}

}
