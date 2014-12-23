<?php
namespace lessp\fr\view;

use \lessp\fr\view\IView;

/**
 * 帮助器父类
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			Helper.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version		    1.0
 */

abstract class Helper
{
	/**
	 * View object
	 * @var \fillp\fr\view\IView
	 */
	protected $view = null;
	
	/**
	 * @param  IView $view
	 */
	public function __construct(IView $view)
	{
		$this->view = $view;
	}
}