<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Mocks;

use HTMLForm;
use PHPUnit\Framework\Assert;

/**
 * @license GPL-2.0-or-later
 */
class HTMLFormSpy {

	private static HTMLForm $form;

	public static function factory( ...$arguments ): HTMLForm {
		self::$form = HTMLForm::factory( ...$arguments );
		return self::$form;
	}

	public static function assertFormFieldData( array $expectedContent ): void {
		Assert::assertSame( $expectedContent, self::$form->mFieldData );
	}

}
