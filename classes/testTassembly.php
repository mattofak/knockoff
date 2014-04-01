<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$model = array('foo' => array( 'bar' => 'baz' ));
$template = array('foo',array('text', 'm.foo.bar', ));

echo $ta->render($template, $model);
