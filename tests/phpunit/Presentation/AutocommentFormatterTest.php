<?php

namespace Wikibase\Schema\Tests\Presentation;

use MediaWikiTestCase;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\Presentation\AutocommentFormatter;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\Presentation\AutocommentFormatter
 */
class AutocommentFormatterTest extends MediaWikiTestCase {

	public function provideAutoComments() {
		yield 'unknown autocomment' => [
			false,
			'foo bar',
			false,
			null,
		];

		yield 'valid new schema comment' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-newschema-nolabel)'
			.'</span></span>'
		];

		yield 'valid new schema comment with pre' => [
			true,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			false,
			'(autocomment-prefix)<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-newschema-nolabel)'
			.'</span></span>'
		];

		yield 'valid new schema comment with post' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_NEWSCHEMA,
			true,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-newschema-nolabel)(colon-separator)'
			.'</span></span>'
		];

		yield 'valid schema text updated comment' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_UPDATED_SCHEMATEXT,
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-update-schema-text)'
			.'</span></span>'
		];
	}

	/**
	 * @dataProvider provideAutoComments
	 */
	public function testFormatAutocomment( $preFlag, $inputComment, $postFlag, $expectedComment ) {
		$this->setUserLang( 'qqx' );
		$formatter = new AutocommentFormatter();

		$actualComment = $formatter->formatAutocomment( $preFlag, $inputComment, $postFlag );

		$this->assertSame( $expectedComment, $actualComment );
	}

}
