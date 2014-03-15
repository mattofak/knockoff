<?php
/**
 * Knockoff extension
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2013; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'Knockoff',
	'author' => array(
		'Gabriel Wicke',
		'Matthew Walker',
	),
	'version' => '0.0.1',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Knockoff',
	'descriptionmsg' => 'knockoff-desc',
);

$dir = dirname( __FILE__ ) . '/';