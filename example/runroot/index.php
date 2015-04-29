<?php
/***************************************************************************
 * 
 * Copyright (c) 2010 , Inc. All Rights Reserved
 * $Id$:index.php,2010/05/07 13:49:09 
 * 
 **************************************************************************/
 
 
 
/**
 * @file index.php
 * @author huqingping
 * @date 2010/05/07 13:49:09
 * @version 1.0 
 * @brief 
 *  
 **/

if (isset($argv)) {
	$opts = getopt('', array('api'));
	if (isset($opts['api'])) {
		define('APP_MODE', 'api');
	} else {
		define('APP_MODE', 'tool');
	}
} else {
	define('APP_MODE', 'web');
}

define('_ROOT', dirname(__DIR__));
$modeRoot = _ROOT.'/mode/'.APP_MODE.'/';
define('FR_ROOT',_ROOT.'/Lessp/fr/');
define('RUN_ROOT',_ROOT.'/runroot/');
define('LIB_ROOT',_ROOT.'/Lessp/lib/');
define('PLUGIN_ROOT',_ROOT.'/plugin/');
define('LOG_ROOT',	$modeRoot.'log/');
define('CONF_ROOT',	_ROOT.'/conf/');
define('TMP_ROOT',	$modeRoot.'tmp/');
define('EXLIB_ROOT',_ROOT.'/exlib/');
define('PAGE_ROOT',_ROOT.'/page/');
define('TOOL_ROOT',_ROOT.'/tool/');
define('API_ROOT',_ROOT.'/api/');

switch(APP_MODE) {
	case 'web':
		require_once FR_ROOT.'app/WebApp.php';
		(new WebApp())->run();
		break;
	case 'tool':
		require_once FR_ROOT.'app/ToolApp.php';
		(new ToolApp())->run();
		break;
	case 'api':
		require_once FR_ROOT.'app/SwooleApp.php';
		SwooleApp::bootstrap();
		break;
}
