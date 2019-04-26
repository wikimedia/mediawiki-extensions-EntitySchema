<?php

namespace EntitySchema\Tests\MediaWiki;

use MediaWikiTestCase;
use SpecialPage;
use Title;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\MediaWiki\WikibaseSchemaHooks;

/**
 * @covers \EntitySchema\MediaWiki\WikibaseSchemaHooks
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaHooksTest extends MediaWikiTestCase {

	public function testOnFormatAutocomments_titleUnset() {
		$comment = null;
		$this->setMwGlobals( 'wgTitle', null );

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			null,
			false
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testOnFormatAutocomments_titleInOtherNamespace() {
		$comment = null;

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			SpecialPage::getTitleFor( 'Version' ),
			false
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testOnFormatAutocomments_unknownAutocomment() {
		$comment = null;

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			false,
			'blah blah',
			false,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ),
			false
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testOnFormatAutocomments_newSchema() {
		$comment = null;
		$this->setUserLang( 'qqx' );

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			true, # usually followed by the label
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ),
			false
		);

		$this->assertFalse( $ret );
		$expected = '<span dir="auto"><span class="autocomment">' .
			'(wikibaseschema-summary-newschema-nolabel)' .
			'(colon-separator)' .
			'</span></span>';
		$this->assertSame( $expected, $comment );
	}

}
