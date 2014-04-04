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
	}

	protected function tearDown() {
		parent::tearDown();
	}

	/**
	 * @dataProvider renderProvider
	 */
	public function testRender( $tassembly, $model, Array $options, $expectation ) {
			$result = TAssembly::render(
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
			$options = Array(
				'partials' => $testObj['partials']['tassembly'],
				'globals' => Array(
					'echo' => function ($foo) { return $foo; },
					'echoJSON' => function ($foo) { return json_encode($foo); },
				),
			);
			$model = $testObj['model'];

			foreach ( $testObj['tests'] as &$test ) {
				$tests[] = array( $test['tassembly'], $model, $options, $test['result'] );
			}

		}
		return $tests;
	}
}
