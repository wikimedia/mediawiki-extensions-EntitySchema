<?php

namespace Wikibase\Schema\MediaWiki;

use Article;
use DatabaseUpdater;
use HistoryPager;
use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use SkinTemplate;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * Hooks utilized by the WikibaseSchema extension
 */
final class WikibaseSchemaHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onCreateDBSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'wbschema_id_counter',
			__DIR__ . '/../../sql/WikibaseSchema.sql'
		);

		$updater->modifyExtensionField(
			'page',
			'page_namespace',
			__DIR__ . '/../../sql/patch-move-page-namespace.sql'
		);
	}

	public static function onExtensionTypes( array &$extTypes ) {
		$extTypes['wikibase'] = 'Wikibase';
	}

	public static function onSkinTemplateNavigation( SkinTemplate $skinTemplate, array &$links ) {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_WBSCHEMA_JSON ) ) {
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
		if ( $article->getTitle()->getNamespace() !== NS_WBSCHEMA_JSON ) {
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
			'wikibaseschema-noschema'
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
		$rev = MediaWikiServices::getInstance()->getRevisionStore()->newRevisionFromRow( $row );

		$wikiPage = $history->getWikiPage();

		if ( $wikiPage->getContentModel() === WikibaseSchemaContent::CONTENT_MODEL_ID
			&& $wikiPage->getLatest() !== $rev->getId()
			&& $wikiPage->getTitle()->quickUserCan( 'edit', $history->getUser() )
			&& !$rev->isDeleted( RevisionRecord::DELETED_TEXT )
		) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			$link = $linkRenderer->makeKnownLink(
				$wikiPage->getTitle(),
				$history->msg( 'wikibaseschema-restoreold' )->text(),
				[],
				[
					'action' => 'edit',
					'restore' => $rev->getId(),
				]
			);

			$html .= ' ' . $history->msg( 'parentheses' )->rawParams( $link )->escaped();
		}
	}

}
