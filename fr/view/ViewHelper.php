<?php
/**
 * 帮助器父类
 * @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
 * @file			Helper.php
 * @author			ronnie<comdeng@live.com>
 * @date			2014-12-23
 * @version		    1.0
 */

abstract class ViewHelper
{
	/**
	 * View object
	 * @var IView
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