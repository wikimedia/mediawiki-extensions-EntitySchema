<?php

namespace Wikibase\Schema\Tests\MediaWiki\Content;

use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @covers \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaContentTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider provideJsonAndHtmlFragments
	 */
	public function testGetParserOutput( array $json, $fragment ) {
		$text = json_encode( $json );
		$content = new WikibaseSchemaContent( $text );

		$parserOutput = $content->getParserOutput( Title::newMainPage() );
		$html = $parserOutput->getText();

		$this->assertContains( $fragment, $html );
	}

	public function provideJsonAndHtmlFragments() {
		return [
			[
				[
					'descriptions' => [ 'en' => 'test' ],
					'schema' => '',
				],
				'<abstract id="wbschema-heading-description">test</abstract>',
			],
			[
				[
					'descriptions' => [ 'en' => '<script>alert("description XSS")</script>' ],
					'schema' => '',
				],
				// exact details of escaping beyond this (> vs &gt;) don’t matter
				'<abstract id="wbschema-heading-description">&lt;script',
			],
			[
				[
					'descriptions' => [ 'en' => 'test' ],
					'schema' => '# basic schema\n_:empty {}',
				],
				'<pre># basic schema\n_:empty {}</pre>',
			],
			[
				[
					'descriptions' => [ 'en' => 'test' ],
					'schema' => '<script>alert("schema XSS")</script>',
				],
				'<pre>&lt;script', // exact details of escaping beyond this (> vs &gt;) don’t matter
			],
		];
	}

}
