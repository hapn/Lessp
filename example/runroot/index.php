<?php
/**
 *  
 * @filesource  index.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc  启动文件
 * @example     
 */

define('SITE_ROOT', dirname(__DIR__));

// 核心框架所在目录
define('FR_ROOT', SITE_ROOT.'/fr/');
// lib所在目录
define('LIB_ROOT', SITE_ROOT.'/lib/');
// page所在目录
define('PAGE_ROOT', SITE_ROOT.'/page/');
// api所在目录
define('API_ROOT', SITE_ROOT.'/api/');
// filter所在目录
define('PLUGIN_ROOT', SITE_ROOT.'/plugin/');
// exlib所在目录
define('EXLIB_ROOT', SITE_ROOT.'/exlib/');
// 日志所在目录
define('LOG_ROOT', SITE_ROOT.'/log/');
// 临时文件所在目录
define('TMP_ROOT', SITE_ROOT.'/tmp/');
// 配置文件所在目录
define('CONF_ROOT', SITE_ROOT.'/conf/');

require_once FR_ROOT.'app/WebApp.php';
$app = new WebApp();
$app->run();