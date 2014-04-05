<?php
require_once(dirname(__FILE__) . '/../classes/TAssembly.php');

$tests = json_decode(file_get_contents(dirname(__FILE__) .
	'/../tests/vectors/TAssembly.PartialsTest.json'), true);
foreach ($tests['tests'] as $test) {
	$model = $tests['model'];
	$model['echo'] = function ($foo) { return $foo; };
	$model['echoJSON'] = function ($foo) { return json_encode($foo); };
	$options = Array(
		'partials' => $tests['partials']['tassembly'],
		'globals' => Array(
			'echo' => function ($foo) { return $foo; },
			'echoJSON' => function () {
				return json_encode(func_get_args());
			},
		),
	);
	$res = TAssembly::render($test['tassembly'], $model, $options);
	if ($res != $test['result']) {
		echo 'FAIL  : ' . json_encode($test['tassembly']) .
			"\n		Expected: " . $test['result'] .
			"\n		Actual  : " . $res . "\n";
	} else {
		echo "PASSED: " . substr(json_encode($test['tassembly']), 0, 80) . "\n";
	}
}
