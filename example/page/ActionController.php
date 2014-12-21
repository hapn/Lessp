<?php

namespace com\demo\page;

use lessp\fr\http\Controller;
/**
 *  
 * @file        ActionController.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 
 * @example     
 */

class ActionController extends Controller
{
	function indexAction()
	{
		$this->set('hello', 'LessP');
	}
}