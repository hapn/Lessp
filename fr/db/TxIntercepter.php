<?php

/**
*   @copyright 		Copyright (C) Jiehun.com.cn 2014 All rights reserved.
*   @file			TrxIntercepter.php
*   @author			ronnie<dengxiaolong@jiehun.com.cn>
*   @date			2014-12-23
*   @version		1.0
*   @desc 
*/

require_once __DIR__.'/Db.php';

final class TxIntercepter
{
	function before(IProxy $proxy,$name,$args)
	{
		TrxScope::beginTx();
	}
	
	function after(IProxy $proxy,$name,$args,$ret)
	{
		TrxScope::commit();
	}
	
	function exception(IProxy $proxy,$name,$args)
	{
		TrxScope::rollback();
	}
}