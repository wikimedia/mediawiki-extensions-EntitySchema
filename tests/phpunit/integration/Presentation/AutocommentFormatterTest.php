<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Presentation;

use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\Presentation\AutocommentFormatter;
use MediaWikiIntegrationTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @group EntitySchemaClient
 *
 * @covers \EntitySchema\Presentation\AutocommentFormatter
 */
class AutocommentFormatterTest extends MediaWikiIntegrationTestCase {

	public static function provideAutoComments(): iterable {
		yield 'unknown autocomment' => [
			false,
			'foo bar',
			false,
			null,
		];

		yield 'valid new schema comment' => [
			false,
			MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-newschema-nolabel)'
			. '</span></span>',
		];

		yield 'valid new schema comment with pre' => [
			true,
			MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			false,
			'(autocomment-prefix)<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-newschema-nolabel)'
			. '</span></span>',
		];

		yield 'valid new schema comment with post' => [
			false,
			MediaWikiRevisionEntitySchemaInserter::AUTOCOMMENT_NEWSCHEMA,
			true,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-newschema-nolabel)(colon-separator)'
			. '</span></span>',
		];

		yield 'valid schema text updated comment' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UPDATED_SCHEMATEXT,
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-update-schema-text)'
			. '</span></span>',
		];

		yield 'valid undo comment with username' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO
			. ':1:username',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-undo-autocomment: 1, Username)'
			. '</span></span>',
		];

		yield 'valid undo comment with ipv4' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO
			. ':1:198.51.100.10',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-undo-autocomment: 1, 198.51.100.10)'
			. '</span></span>',
		];

		yield 'valid undo comment with ipv6' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO
			. ':1:2001:db8::1',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-undo-autocomment: 1, 2001:db8::1)'
			. '</span></span>',
		];

		yield 'valid restore comment with username' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE
			. ':1:username',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-restore-autocomment: 1, Username)'
			. '</span></span>',
		];

		yield 'valid restore comment with ipv4' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE
			. ':1:198.51.100.10',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-restore-autocomment: 1, 198.51.100.10)'
			. '</span></span>',
		];

		yield 'valid restore comment with ipv6' => [
			false,
			MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE
			. ':1:2001:db8::1',
			false,
			'<span dir="auto"><span class="autocomment">'
			. '(entityschema-summary-restore-autocomment: 1, 2001:db8::1)'
			. '</span></span>',
		];
	}

	/**
	 * @dataProvider provideAutoComments
	 */
	public function testFormatAutocomment(
		bool $preFlag,
		string $inputComment,
		bool $postFlag,
		?string $expectedComment
	) {
		$this->setUserLang( 'qqx' );
		$formatter = new AutocommentFormatter();

		$actualComment = $formatter->formatAutocomment( $preFlag, $inputComment, $postFlag );

		$this->assertSame( $expectedComment, $actualComment );
	}

}
