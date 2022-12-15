<?php

namespace EntitySchema\MediaWiki;

use Article;
use DatabaseUpdater;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\AutocommentFormatter;
use HistoryPager;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MWException;
use SkinTemplate;
use Title;
use WikiImporter;

/**
 * Hooks utilized by the EntitySchema extension
 *
 * @license GPL-2.0-or-later
 */
final class EntitySchemaHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onCreateDBSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'entityschema_id_counter',
			dirname( __DIR__, 2 ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}

	public static function onExtensionTypes( array &$extTypes ) {
		if ( !isset( $extTypes['wikibase'] ) ) {
			$extTypes['wikibase'] = 'Wikibase';
		}
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return;
		}

		unset( $links['views']['edit'] );
	}

	/**
	 * Handler for the BeforeDisplayNoArticleText called by Article.
	 * We implement this solely to replace the standard message that
	 * is shown when a Schema does not exists.
	 *
	 * @param Article $article
	 *
	 * @return bool
	 */
	public static function onBeforeDisplayNoArticleText( Article $article ) {
		if ( $article->getTitle()->getNamespace() !== NS_ENTITYSCHEMA_JSON ) {
			return true;
		}

		$context = $article->getContext();
		$dir = $context->getLanguage()->getDir();
		$lang = $context->getLanguage()->getHtmlCode();

		$outputPage = $context->getOutput();
		$outputPage->wrapWikiMsg(
			Html::element( 'div', [
				'class' => "noarticletext mw-content-$dir",
				'dir' => $dir,
				'lang' => $lang,
			], '$1' ),
			'entityschema-noschema'
		);

		return false;
	}

	/**
	 * Modify line endings on history page.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryLineEnding
	 *
	 * @param HistoryPager $history
	 * @param object &$row
	 * @param string &$html
	 * @param array &$classes
	 */
	public static function onPageHistoryLineEnding(
		HistoryPager $history,
		&$row,
		&$html,
		array &$classes
	) {
		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();
		$rev = $services->getRevisionStore()->newRevisionFromRow( $row );
		$title = $history->getTitle();
		$contentModel = $title->getContentModel();
		$latestRevId = $title->getLatestRevID();

		if ( $contentModel === EntitySchemaContent::CONTENT_MODEL_ID
			&& $latestRevId !== $rev->getId()
			&& $pm->quickUserCan( 'edit', $history->getUser(), $title )
			&& !$rev->isDeleted( RevisionRecord::DELETED_TEXT )
		) {
			$linkRenderer = $services->getLinkRenderer();
			$link = $linkRenderer->makeKnownLink(
				$title,
				$history->msg( 'entityschema-restoreold' )->text(),
				[],
				[
					'action' => 'edit',
					'restore' => $rev->getId(),
				]
			);

			$html .= ' ' . $history->msg( 'parentheses' )->rawParams( $link )->escaped();
		}
	}

	/**
	 * Handler for the FormatAutocomments hook, used to translate parts of edit summaries
	 * into the user language. Only supports a fixed set of autocomments.
	 *
	 * @param string|null &$comment The comment HTML. Initially null; if set to a string, then
	 * {@link \MediaWiki\CommentFormatter\CommentParser::doSectionLinks()} will skip the default formatting.
	 * In that case, the actual autocomment should be wrapped in <span dir="auto"><span class="autocomment">.
	 * @param bool $pre Whether any text appears in the summary before this autocomment.
	 * If true, we insert the autocomment-prefix before the autocomment
	 * (outside the two <span>s) to separate it from that.
	 * @param string $auto The autocomment content (without the surrounding comment marks)
	 * @param bool $post Whether any text appears in the summary after this autocomment.
	 * If true, we append the colon-separator after the autocomment (still inside the two <span>s)
	 * to separate it from that.
	 * @param Title|null $title The title to which the comment applies. A null $title is taken to
	 * refer to the current page ($wgTitle), though that’s not quite clear from Linker’s documentation.
	 * @param bool $local If true, don’t actually use the $title for links, e. g. generate
	 * <a href="#foo"> instead of <a href="/wiki/Namespace:Title#foo">. Unused here.
	 *
	 * @return null|false
	 */
	public static function onFormatAutocomments( &$comment, $pre, $auto, $post, $title, $local ) {
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

		$autocommentFormatter = new AutocommentFormatter();
		$formattedComment = $autocommentFormatter->formatAutocomment( $pre, $auto, $post );
		if ( $formattedComment !== null ) {
			$comment = $formattedComment;
			return false;
		}

		return null;
	}

	/**
	 * @see ContentHandler::canBeUsedOn()
	 *
	 * @param string $modelId The content model ID.
	 * @param Title $title The title where the content model may or may not be used on.
	 * @param bool &$ok Whether the content model can be used on the title or not.
	 *
	 * @return null|false
	 */
	public static function onContentModelCanBeUsedOn( $modelId, Title $title, &$ok ) {
		if (
			$title->inNamespace( NS_ENTITYSCHEMA_JSON ) &&
			$modelId !== EntitySchemaContent::CONTENT_MODEL_ID
		) {
			$ok = false;
			return false; // skip other hooks
		}
		// the other direction is guarded by EntitySchemaContentHandler::canBeUsedOn()

		return null;
	}

	public static function onImportHandleRevisionXMLTag(
		WikiImporter $importer,
		array $pageInfo,
		array $revisionInfo
	) {
		if (
			array_key_exists( 'model', $revisionInfo ) &&
			$revisionInfo['model'] === EntitySchemaContent::CONTENT_MODEL_ID
		) {
			throw new MWException(
				'To avoid ID conflicts, the import of Schemas is not supported.'
			);
		}
	}

	/**
	 * Handler for the TitleGetRestrictionTypes hook.
	 *
	 * Implemented to prevent people from protecting pages from being
	 * created or moved in a Schema namespace (which is pointless).
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleGetRestrictionTypes
	 *
	 * @param Title $title
	 * @param string[] &$types The types of protection available
	 */
	public static function onTitleGetRestrictionTypes( Title $title, array &$types ) {
		if ( $title->getNamespace() === NS_ENTITYSCHEMA_JSON ) {
			// Remove create and move protection for Schema namespaces
			$types = array_diff( $types, [ 'create', 'move' ] );
		}
	}

}
