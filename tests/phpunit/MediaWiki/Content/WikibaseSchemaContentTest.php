<?php

namespace Wikibase\Schema\Tests\MediaWiki\Content;

use Title;
use SpecialPage;
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
	public function testGetParserOutput( array $json, array $fragments ) {
		$text = json_encode( $json );
		$content = new WikibaseSchemaContent( $text );

		$parserOutput = $content->getParserOutput(
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O16354758' )
		);
		$html = $parserOutput->getText();

		foreach ( $fragments as $fragment ) {
			$this->assertContains( $fragment, $html );
		}
	}

	public function provideJsonAndHtmlFragments() {
		return [
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[ '<td class="wbschema-description" lang="en">test</td>' ],
			],
			[
				[
					'descriptions' => [
						'en' => '<script>alert("description XSS")</script>',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				// exact details of escaping beyond this (> vs &gt;) don’t matter
				[ '<td class="wbschema-description" lang="en">&lt;script' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '_:empty {}',
					'serializationVersion' => '3.0',
				],
				[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">_:empty {}</pre>' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '<script>alert("schema XSS")</script>',
					'serializationVersion' => '3.0',
				],
				// exact details of escaping beyond this (> vs &gt;) don’t matter
				[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">&lt;script' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'english description',
						'de' => 'deutsche Beschreibung',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[
					'<td class="wbschema-description" lang="en">english description</td>',
					'<td class="wbschema-description" lang="de">deutsche Beschreibung</td>',
				]
			],
			[
				[
					'descriptions' => [
						'en' => 'english description',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[
					SpecialPage::getTitleValueFor(
						'SetSchemaLabelDescriptionAliases',
						'O16354758/en'
					)->getText(),
					':O16354758&amp;action=edit'
				]
			],
		];
	}

}
