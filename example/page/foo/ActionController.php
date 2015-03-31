<?php
/**
 * 
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @filesource		ActionController.php
 * @author			ronnie<comdeng@live.com>
 * @since			2014-12-23
 * @version		    1.0
 */

class Foo_Controller extends PageController
{
	function bar_action()
	{
		$this->set('foo', 'bar');
		$this->set('hello', '我来自/foo/bar');
	}
}