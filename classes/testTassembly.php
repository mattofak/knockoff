<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$model = array('foo' => array( 'bar' => Array('baz', 'quux', 'booo') ));
$template = json_decode('["foo: ",["foreach",{"data":"m.foo.bar","tpl":["\n",["text","m"]]}]]', true);

echo $ta->render($template, $model) . "\n";
