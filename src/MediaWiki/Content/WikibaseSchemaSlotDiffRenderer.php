<?php

namespace EntitySchema\MediaWiki\Content;

use Content;
use ContentHandler;
use Diff\DiffOp\AtomicDiffOp;
use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Html;
use IContextSource;
use MessageLocalizer;
use RequestContext;
use SlotDiffRenderer;
use TextSlotDiffRenderer;
use UnexpectedValueException;
use EntitySchema\Services\Diff\SchemaDiffer;
use EntitySchema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaSlotDiffRenderer extends SlotDiffRenderer {

	/** @var SchemaConverter */
	private $schemaConverter;

	/** @var SchemaDiffer */
	private $schemaDiffer;

	/** @var TextSlotDiffRenderer */
	private $textSlotDiffRenderer;

	/** @var MessageLocalizer */
	private $msgLocalizer;

	public function __construct(
		IContextSource $context = null,
		TextSlotDiffRenderer $textSlotDiffRenderer = null
	) {
		if ( $context === null ) {
			$context = RequestContext::getMain();
		}

		if ( $textSlotDiffRenderer === null ) {
			$textSlotDiffRenderer = ContentHandler::getForModelID( CONTENT_MODEL_TEXT )
				->getSlotDiffRenderer( $context );
			if ( !is_a( $textSlotDiffRenderer, TextSlotDiffRenderer::class ) ) {
				throw new UnexpectedValueException( 'Expected a TextSlotDiffRenderer' );
			}
		}

		$this->schemaDiffer = new SchemaDiffer();
		$this->schemaConverter = new SchemaConverter();
		$this->textSlotDiffRenderer = $textSlotDiffRenderer;
		$this->msgLocalizer = $context;
	}

	/**
	 * @phan-suppress PhanParamSignatureMismatch
	 *
	 * @param WikibaseSchemaContent|null $oldContent
	 * @param WikibaseSchemaContent|null $newContent
	 *
	 * @return string
	 */
	public function getDiff( Content $oldContent = null, Content $newContent = null ) {
		$this->normalizeContents( $oldContent, $newContent, WikibaseSchemaContent::class );

		$diff = $this->schemaDiffer->diffSchemas(
			$this->schemaConverter->getFullArraySchemaData( $oldContent->getText() ),
			$this->schemaConverter->getFullArraySchemaData( $newContent->getText() )
		);

		return $this->renderSchemaDiffRows( $diff );
	}

	public function renderSchemaDiffRows( Diff $diff ) {
		// split $diff into labels/descriptions/aliases (renderDiffOp())
		// and schema (renderTextDiff())
		$nameBadgeDiffOps = [];
		if ( isset( $diff['labels'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-label' )->text()
				] = $diff['labels'];
		}
		if ( isset( $diff['descriptions'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-description' )->text()
			] = $diff['descriptions'];
		}
		if ( isset( $diff['aliases'] ) ) {
			$nameBadgeDiffOps[
				$this->msgLocalizer->msg( 'entityschema-diff-aliases' )->text()
			] = $diff['aliases'];
		}
		$nameBadgeDiff = $this->renderDiffOp( [], new Diff( $nameBadgeDiffOps, true ) );

		if ( isset( $diff['schemaText'] ) ) {
			$schemaDiff = $this->renderTextDiff(
				$this->msgLocalizer->msg( 'entityschema-diff-schema' )->text(),
				$diff['schemaText']
			);
		} else {
			$schemaDiff = '';
		}

		return $nameBadgeDiff . $schemaDiff;
	}

	private function renderDiffOp( array $keys, DiffOp $diffOp ) {
		if ( $diffOp instanceof Diff ) {
			$output = '';
			foreach ( $diffOp->getOperations() as $key => $op ) {
				$moreKeys = $keys;
				$moreKeys[] = $key;
				$output .= $this->renderDiffOp( $moreKeys, $op );
			}
			return $output;
		}

		if ( $diffOp instanceof DiffOpRemove || $diffOp instanceof DiffOpChange ) {
			$leftContext = $this->diffContext( implode( ' / ', $keys ) );
			$leftTds = $this->diffRemovedLine( $diffOp->getOldValue() );
		} else {
			$leftContext = $this->diffContext( '' );
			$leftTds = $this->diffBlankLine();
		}

		if ( $diffOp instanceof DiffOpAdd || $diffOp instanceof DiffOpChange ) {
			$rightContext = $this->diffContext( implode( ' / ', $keys ) );
			$rightTds = $this->diffAddedLine( $diffOp->getNewValue() );
		} else {
			$rightContext = $this->diffContext( '' );
			$rightTds = $this->diffBlankLine();
		}

		$context = $this->diffRow( $leftContext . $rightContext );
		$changes = $this->diffRow( $leftTds . $rightTds );

		return $context . $changes;
	}

	/**
	 * @suppress PhanUndeclaredMethod
	 */
	private function renderTextDiff( $key, AtomicDiffOp $diffOp ) {
		if ( $diffOp instanceof DiffOpAdd || $diffOp instanceof DiffOpRemove ) {
			return $this->renderDiffOp( [ $key ], $diffOp );
		}

		/** @var DiffOpChange $diffOp */
		// Line 1 → schema / Line 1
		return preg_replace(
			'/<td[^>]* class="diff-lineno"[^>]*>/',
			'$0' . htmlspecialchars( $key ) . ' / ',
			$this->textSlotDiffRenderer->getTextDiff(
				trim( $diffOp->getOldValue() ),
				trim( $diffOp->getNewValue() )
			)
		);
	}

	private function diffRow( $content ) {
		return Html::rawElement(
			'tr',
			[],
			$content
		);
	}

	private function diffContext( $context ) {
		return Html::element(
			'td',
			[ 'colspan' => '2', 'class' => 'diff-lineno' ],
			$context
		);
	}

	private function diffBlankLine() {
		return Html::element( 'td', [ 'colspan' => '2' ] );
	}

	private function diffMarker( $marker ) {
		return Html::element(
			'td',
			[ 'class' => 'diff-marker' ],
			$marker
		);
	}

	private function diffAddedLine( $line ) {
		return $this->diffMarker( '+' ) . Html::element(
			'td',
			[ 'class' => 'diff-addedline' ],
			$line
		);
	}

	private function diffRemovedLine( $line ) {
		return $this->diffMarker( '−' ) . Html::element(
			'td',
			[ 'class' => 'diff-deletedline' ],
			$line
		);
	}

}
