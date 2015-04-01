<?php

/**
 *  
 * @filesource  ActionController.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc 
 * @example     
 */

class _Controller extends PageController
{
	function indexAction()
	{
		$this->set('hello1', 'Lessp');
		
		$this->forward('/foo/bar');
	}
}