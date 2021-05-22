<?php

namespace EntitySchema\MediaWiki\Content;

use Action;
use Article;
use Content;
use DifferenceEngine;
use EntitySchema\DataAccess\SchemaEncoder;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\MediaWiki\Actions\RestoreViewAction;
use EntitySchema\MediaWiki\Actions\SchemaEditAction;
use EntitySchema\MediaWiki\Actions\SchemaSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\MediaWiki\Actions\UndoViewAction;
use EntitySchema\MediaWiki\UndoHandler;
use EntitySchema\Presentation\InputValidator;
use IContextSource;
use JsonContentHandler;
use Language;
use LogicException;
use MediaWiki\MediaWikiServices;
use Page;
use RequestContext;
use SlotDiffRenderer;
use Title;
use WikiPage;

/**
 * Content handler for the EntitySchema content
 *
 * @license GPL-2.0-or-later
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
			'edit' => function( Page $article, IContextSource $context = null ) {
				return $this->getActionOverridesEdit( $article, $context );
			},
			'submit' => function( Page $article, IContextSource $context = null ) {
				return $this->getActionOverridesSubmit( $article, $context );
			},
		];
	}

	/**
	 * @param Article|Page $page
	 * @param IContextSource|null $context
	 * @return Action|callable
	 */
	private function getActionOverridesEdit(
		Page $page,
		?IContextSource $context
	) {
		global $wgEditSubmitButtonLabelPublish;

		$article = $this->prepareActionForBC( $page, $context, __METHOD__ );
		$context = $context ?? RequestContext::getMain();

		if ( $article->getPage()->getRevisionRecord() === null ) {
			return Action::factory( 'view', $article, $context );
		}

		$req = $context->getRequest();

		if (
			$req->getCheck( 'undo' )
			|| $req->getCheck( 'undoafter' )
		) {
			return new UndoViewAction(
				$article,
				new EntitySchemaSlotDiffRenderer( $context ),
				$context
			);
		}

		if ( $req->getCheck( 'restore' ) ) {
			return new RestoreViewAction(
				$article,
				new EntitySchemaSlotDiffRenderer( $context ),
				$context
			);
		}

		// TODo: check redirect?
		// !$article->isRedirect()
		return new SchemaEditAction(
			$article,
			new InputValidator(
				$context,
				MediaWikiServices::getInstance()->getMainConfig()
			),
			$wgEditSubmitButtonLabelPublish,
			$context
		);
	}

	/**
	 * @param Article|Page $page
	 * @param IContextSource|null $context
	 * @return RestoreSubmitAction|UndoSubmitAction|string
	 */
	private function getActionOverridesSubmit(
		Page $page,
		?IContextSource $context
	) {
		$article = $this->prepareActionForBC( $page, $context, __METHOD__ );
		$context = $context ?? RequestContext::getMain();
		$req = $context->getRequest();

		if (
			$req->getCheck( 'undo' )
			|| $req->getCheck( 'undoafter' )
		) {
			return new UndoSubmitAction( $article, $context );
		}

		if ( $req->getCheck( 'restore' ) ) {
			return new RestoreSubmitAction( $article, $context );
		}

		return SchemaSubmitAction::class;
	}

	private function prepareActionForBC(
		Page $article,
		?IContextSource $context,
		string $action
	): Article {
		if ( $article instanceof WikiPage ) {
			$article = Article::newFromWikiPage(
				$article,
				$context ?? RequestContext::getMain()
			);
		} elseif ( !$article instanceof Article ) {
			throw new LogicException(
				"Hook: {$action} call with unknown page: " . get_class( $article )
			);
		}

		return $article;
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
	 * @since 1.37 only accepts Content objects
	 *
	 * @param Content $baseContent The current text
	 * @param Content $undoFromContent The content of the revision to undo
	 * @param Content $undoToContent Must be from an earlier revision than $undo
	 * @param bool $undoIsLatest Set true if $undo is from the current revision (since 1.32)
	 *
	 * @return Content|false
	 */
	public function getUndoContent(
		Content $baseContent,
		Content $undoFromContent,
		Content $undoToContent,
		$undoIsLatest = false
	) {
		if ( $undoIsLatest ) {
			return $undoToContent;
		}

		// Make sure correct subclass
		if ( !$baseContent instanceof EntitySchemaContent ||
			!$undoFromContent instanceof EntitySchemaContent ||
			!$undoToContent instanceof EntitySchemaContent
		) {
			return false;
		}

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
	 * @note The html representation of Schemas depends on the user language, so
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
