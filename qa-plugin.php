<?php

/*
	Plugin Name: Custom RSS Plugin
	Plugin URI:
	Plugin Description: Add RSS of a question with pictures
	Plugin Version: 1.0
	Plugin Date: 2016-0-0
	Plugin Author: 38qa.net
	Plugin Author URI: http://www.question2answer.org/
	Plugin License: GPLv2
	Plugin Minimum Question2Answer Version: 1.5
	Plugin Update Check URI:
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

// process
qa_register_plugin_module('process', 'qa-custom-rss-process.php', 'qa_custom_rss_process', 'Custom RSS Process');
