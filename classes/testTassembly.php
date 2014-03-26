<?php
require_once('./TAssembly.php');

$ta = new TAssembly\TAssembly();

$model = array('foo' => 'bar');
$template = array('foo',array('text', 'm.foo', ));

echo $ta->render($template, $model);
