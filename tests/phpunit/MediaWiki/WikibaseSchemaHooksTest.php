<?php

namespace Wikibase\Schema\Tests\MediaWiki;

use MediaWikiTestCase;
use SpecialPage;
use Title;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\MediaWiki\WikibaseSchemaHooks;

/**
 * @covers \Wikibase\Schema\MediaWiki\WikibaseSchemaHooks
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
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
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
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
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
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
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
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			true, # usually followed by the label
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			false
		);

		$this->assertFalse( $ret );
		$expected = '<span dir="auto"><span class="autocomment">' .
			'(wikibaseschema-summary-newschema-nolabel)' .
			'(colon-separator)' .
			'</span></span>';
		$this->assertSame( $expected, $comment );
	}

	public function testOnFormatAutocomments_newSchema_noPost() {
		$comment = null;
		$this->setUserLang( 'qqx' );

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			false, # unclear when this should happen
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			false
		);

		$this->assertFalse( $ret );
		$expected = '<span dir="auto"><span class="autocomment">' .
			'(wikibaseschema-summary-newschema-nolabel)' .
			'</span></span>';
		$this->assertSame( $expected, $comment );
	}

	public function testOnFormatAutocomments_newSchema_pre() {
		$comment = null;
		$this->setUserLang( 'qqx' );

		$ret = WikibaseSchemaHooks::onFormatAutocomments(
			$comment,
			true, # unclear when this should happen
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			true,
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			false
		);

		$this->assertFalse( $ret );
		$expected = '(autocomment-prefix)<span dir="auto"><span class="autocomment">' .
			'(wikibaseschema-summary-newschema-nolabel)' .
			'(colon-separator)' .
			'</span></span>';
		$this->assertSame( $expected, $comment );
	}

}
