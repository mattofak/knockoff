<?php
namespace TAssembly;
require_once( __DIR__ . '/../classes/TAssembly.php');

/**
 * @group Extensions/Knockoff
 */
class TAssemblyTest extends \PHPUnit_Framework_TestCase {
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
	public function testRender( $tassembly, $model, TAssemblyOptions $options, $expectation ) {
		$options->globals['echo'] = function ($foo) { return $foo; };
		$options->globals['echoJSON'] = function ($foo) { return json_encode($foo); };
		$result = $this->ta->render(
			$tassembly,
			$model,
			$options
		);
		$this->assertEquals( $expectation, $result );
	}

	public function renderProvider() {
		$tests = array();
		$files = glob( __DIR__ . '/vectors/TAssembly.*.json' );
		foreach ( $files as $file ) {
			$testObj = json_decode( file_get_contents( $file ), true );
			$options = new TAssemblyOptions();
			$options->partials = $testObj['partials']['tassembly'];
			$model = $testObj['model'];

			foreach ( $testObj['tests'] as &$test ) {
				$tests[] = array( $test['tassembly'], $model, $options, $test['result'] );
			}

		}
		return $tests;
	}
}
