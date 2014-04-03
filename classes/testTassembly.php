<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$tests = json_decode(file_get_contents('./tests.json'), true);
foreach ($tests['tests'] as $test) {
	$model = $tests['model'];
	$model['test'] = function ($foo) { return $foo . 'test'; };
	$options = new TAssembly\TAssemblyOptions();
	$options->partials = $tests['partials']['tassembly'];
	$res = $ta->render($test['tassembly'], $model, $options);
	if ($res != $test['result']) {
		echo 'FAIL  : ' . json_encode($test['tassembly']) .
			"\n		Expected: " . $test['result'] .
			"\n		Actual  : " . $res . "\n";
	} else {
		echo "PASSED: " . substr(json_encode($test['tassembly']), 0, 80) . "\n";
	}
}
