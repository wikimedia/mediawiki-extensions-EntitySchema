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

		yield 'valid undo comment with username' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_UNDO
			. ':1:username',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-undo-autocomment: 1, Username)'
			.'</span></span>'
		];

		yield 'valid undo comment with ipv4' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_UNDO
			. ':1:198.51.100.10',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-undo-autocomment: 1, 198.51.100.10)'
			.'</span></span>'
		];

		yield 'valid undo comment with ipv6' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_UNDO
			. ':1:2001:db8::1',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-undo-autocomment: 1, 2001:db8::1)'
			.'</span></span>'
		];

		yield 'valid restore comment with username' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_RESTORE
			. ':1:username',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-restore-autocomment: 1, Username)'
			.'</span></span>'
		];

		yield 'valid restore comment with ipv4' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_RESTORE
			. ':1:198.51.100.10',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-restore-autocomment: 1, 198.51.100.10)'
			.'</span></span>'
		];

		yield 'valid restore comment with ipv6' => [
			false,
			MediaWikiRevisionSchemaWriter::AUTOCOMMENT_RESTORE
			. ':1:2001:db8::1',
			false,
			'<span dir="auto"><span class="autocomment">'
			.'(wikibaseschema-summary-restore-autocomment: 1, 2001:db8::1)'
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
