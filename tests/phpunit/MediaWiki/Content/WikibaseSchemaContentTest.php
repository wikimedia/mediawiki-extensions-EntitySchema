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
					'description' => [ 'en' => 'test' ],
					'schema' => '',
				],
				'<h3>test</h3>',
			],
			[
				[
					'description' => [ 'en' => '<script>alert("description XSS")</script>' ],
					'schema' => '',
				],
				'<h3>&lt;script', // exact details of escaping beyond this (> vs &gt;) don’t matter
			],
			[
				[
					'description' => [ 'en' => 'test' ],
					'schema' => '# basic schema\n_:empty {}',
				],
				'<pre># basic schema\n_:empty {}</pre>',
			],
			[
				[
					'description' => [ 'en' => 'test' ],
					'schema' => '<script>alert("schema XSS")</script>',
				],
				'<pre>&lt;script', // exact details of escaping beyond this (> vs &gt;) don’t matter
			],
		];
	}

}
