<?php

namespace Wikibase\Schema;

use Article;
use DatabaseUpdater;
use Html;
use SkinTemplate;

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
			__DIR__ . '/../sql/WikibaseSchema.sql'
		);

		$updater->modifyExtensionField(
			'page',
			'page_namespace',
			__DIR__ . '/../sql/patch-move-page-namespace.sql'
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

		if ( $title->getLength() !== 0 ) {
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

}
