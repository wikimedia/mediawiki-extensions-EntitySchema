<?php

namespace EntitySchema\MediaWiki\Content;

use Action;
use Article;
use Content;
use DifferenceEngine;
use IContextSource;
use JsonContentHandler;
use Language;
use LogicException;
use MediaWiki\MediaWikiServices;
use Page;
use RequestContext;
use Revision;
use SlotDiffRenderer;
use Title;
use EntitySchema\DataAccess\SchemaEncoder;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreViewAction;
use EntitySchema\MediaWiki\Actions\SchemaEditAction;
use EntitySchema\MediaWiki\Actions\SchemaSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoViewAction;
use EntitySchema\MediaWiki\UndoHandler;
use EntitySchema\Presentation\InputValidator;
use WikiPage;

/**
 * Content handler for the EntitySchema content
 */
class EntitySchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = EntitySchemaContent::CONTENT_MODEL_ID ) {
		parent::__construct( $modelId );
	}

	protected function getContentClass() {
		return EntitySchemaContent::class;
	}

	public function createDifferenceEngine( IContextSource $context,
		$old = 0, $new = 0,
		$rcid = 0, // FIXME: Deprecated, no longer used
		$refreshCache = false, $unhide = false
	) {
		return new DifferenceEngine( $context, $old, $new, $rcid, $refreshCache, $unhide );
	}

	protected function getSlotDiffRendererInternal( IContextSource $context ): SlotDiffRenderer {
		return new EntitySchemaSlotDiffRenderer( $context );
	}

	/**
	 * @see ContentHandler::getPageViewLanguage
	 *
	 * This implementation returns the user language, because Schemas get rendered in
	 * the user's language. The PageContentLanguage hook is bypassed.
	 *
	 * @param Title        $title   (unused) the page to determine the language for.
	 * @param Content|null $content (unused) the page's content
	 *
	 * @return Language The page's language
	 */
	public function getPageViewLanguage( Title $title, Content $content = null ) {
		$context = RequestContext::getMain();
		return $context->getLanguage();
	}

	public function canBeUsedOn( Title $title ) {
		return $title->inNamespace( NS_ENTITYSCHEMA_JSON ) && parent::canBeUsedOn( $title );
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
						new EntitySchemaSlotDiffRenderer( $context ),
						$context
					);
				}

				if ( $req->getCheck( 'restore' ) ) {
					return new RestoreViewAction(
						$page,
						new EntitySchemaSlotDiffRenderer( $context ),
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

		return new EntitySchemaContent( SchemaEncoder::getPersistentRepresentation(
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
	 * EntitySchemaContent::getParserOutput needs to make sure
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
