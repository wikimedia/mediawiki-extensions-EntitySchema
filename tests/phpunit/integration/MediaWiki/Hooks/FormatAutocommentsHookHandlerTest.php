<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki;

use EntitySchema\DataAccess\MediaWikiRevisionSchemaInserter;
use EntitySchema\MediaWiki\Hooks\FormatAutocommentsHookHandler;
use EntitySchema\Presentation\AutocommentFormatter;
use MediaWikiIntegrationTestCase;
use SpecialPage;
use Title;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\FormatAutocommentsHookHandler
 *
 * @license GPL-2.0-or-later
 */
class FormatAutocommentsHookHandlerTest extends MediaWikiIntegrationTestCase {
	public function testTitleUnset() {
		$comment = null;
		$this->setMwGlobals( 'wgTitle', null );
		$autocommentFormatter = new AutocommentFormatter();
		$hookHandler = new FormatAutocommentsHookHandler( $autocommentFormatter );
		$ret = $hookHandler->onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			null,
			false,
			null
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testTitleInOtherNamespace() {
		$comment = null;
		$autocommentFormatter = new AutocommentFormatter();
		$hookHandler = new FormatAutocommentsHookHandler( $autocommentFormatter );
		$ret = $hookHandler->onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			SpecialPage::getTitleFor( 'Version' ),
			false,
			null
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testWithUnknownAutocomment() {
		$comment = null;
		$autocommentFormatter = new AutocommentFormatter();
		$hookHandler = new FormatAutocommentsHookHandler( $autocommentFormatter );
		$ret = $hookHandler->onFormatAutocomments(
			$comment,
			false,
			'blah blah',
			false,
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ),
			false,
			null
		);

		$this->assertNull( $ret );
		$this->assertNull( $comment );
	}

	public function testWithNewSchema() {
		$comment = null;
		$this->setUserLang( 'qqx' );
		$autocommentFormatter = new AutocommentFormatter();
		$hookHandler = new FormatAutocommentsHookHandler( $autocommentFormatter );
		$ret = $hookHandler->onFormatAutocomments(
			$comment,
			false,
			MediaWikiRevisionSchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			true, # usually followed by the label
			Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ),
			false,
			null
		);

		$this->assertFalse( $ret );
		$expected = '<span dir="auto"><span class="autocomment">' .
			'(entityschema-summary-newschema-nolabel)' .
			'(colon-separator)' .
			'</span></span>';
		$this->assertSame( $expected, $comment );
	}
}
