<?php
namespace TAssembly;

/**
 * @group Extensions/Knockoff
 */
class TAssemblyTest extends \MediaWikiTestCase {
	/** @var TAssembly */
	protected $ta;

	protected function setUp() {
		parent::setUp();
		$this->ta = new TAssembly();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	/**
	 * @dataProvider renderProvider
	 */
	public function testRender( $file ) {
		$testObj = json_decode( file_get_contents( $file ), true );

		$options = new TAssemblyOptions();
		$options->partials = &$testObj['partials'];

		$model = &$testObj['model'];

		foreach ( $testObj['tests'] as $test ) {
			$result = $this->ta->render(
				$test['tassembly'],
				$model,
				$options
			);
			$this->assertEquals( $test['result'], $result );
		}
	}

	public function renderProvider() {
		$files = glob( __DIR__ . '/vectors/TAssembly.*.json' );
		foreach ( $files as &$file ) {
			$file = array( $file );
		}
		return $files;
	}
}