<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$tests = json_decode(file_get_contents('./tests.json'), true);
foreach ($tests['tests'] as $test) {
	$res = $ta->render($test['tassembly'], $tests['model']);
	if ($res != $test['result']) {
		echo 'Failure for ' . json_encode($test['tassembly']) .
			"\n Expected: " . $test['result'] .
			"\n Actual  : " . $res . "\n";
	}
}
