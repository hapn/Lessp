<?php
use \lessp\fr\conf\Conf;
/**
 *  
 * @filesource  lessp.conf.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc
 * @example     
 */

Conf::set('lessp.debug', 'manual');
Conf::set('lessp.disable_db', true);
Conf::set('lessp.ns', 'com\\demo\\');

Conf::set('lessp.disable_db', true);

Conf::set('db.conf', array(	
	'dbs' => array(
		
	),
	'db_pool' => array(),
	'text_db' => array(),
));