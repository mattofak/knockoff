<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$model = array('foo' => array( 'bar' => Array('baz', 'quux', 'booo') ));
$template = array('foo: ', array('foreach', array('data' => 'm.foo.bar', 'tpl' => array(array('text', 'm')))));

echo $ta->render($template, $model) . "\n";
