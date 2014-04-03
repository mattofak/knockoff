<?php
/**
 * @file
 * @ingroup Extensions
 * @copyright 2013; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

// Register hooks
$wgHooks['UnitTestsList'][] = function( &$files ) {
	$files = array_merge( $files, glob( __DIR__ . '/tests/*Test.php' ) );
	return true;
};