<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use DatabaseUpdater;
use EntitySchema\Presentation\AutocommentFormatter;
use SkinTemplate;
use Title;

/**
 * Hooks utilized by the EntitySchema extension
 *
 * @license GPL-2.0-or-later
 */
final class EntitySchemaHooks {

	public static function onCreateDBSchema( DatabaseUpdater $updater ): void {
		$updater->addExtensionTable(
			'entityschema_id_counter',
			dirname( __DIR__, 2 ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ): void {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return;
		}

		unset( $links['views']['edit'] );
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
	public static function onFormatAutocomments(
		?string &$comment,
		bool $pre,
		string $auto,
		bool $post,
		?Title $title,
		bool $local
	): ?bool {
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
}
