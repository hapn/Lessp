<?php
namespace com\demo\page\foo;

use lessp\fr\http\Controller;
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @filesource		ActionController.php
 * @author			ronnie<comdeng@live.com>
 * @since			2014-12-23
 * @version		    1.0
 */

class ActionController extends Controller
{
	function barAction()
	{
		$this->set('foo', 'bar');
		$this->set('hello', '我来自/foo/bar');
	}
}