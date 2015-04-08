<?php
/**
 *  
 * @filesource        Api.php
 * @author      ronnie<comdeng@live.com>
 * @since        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.hapn 2014 All rights reserved.
 * @desc 
 * @example     
 */

class Api
{
	private $caller = null;
	private static $proxies = array();
	private static $configure = array();
	
	private static $apipath = '';
	private static $confpath = '';
	private static $apins = '';
	
	private $intercepters = array();
	private static $globalIntercepters = array();
	private static $gendata = false;
	private static $gendslpath = '';
	private static $encoding = 'UTF-8';

	private function __construct(IProxy $proxy)
	{
		$this->caller = $proxy;
	}

	/**
	 * 初始化
	 * @param array $conf
	 * <code>array(
	 * 	'mod' => array(
	 * 		'class'		=> 'RpcProxy|HttpRpcProxy|HttpJsonProxy',
	 * 		'options'	=> array(),
	 * 	),
	 *  'server' => array(
	 *  	
	 *  ),
	 * )</code>
	 * @param array $pathroot
	 * <code>array(
	 * 	'api_root' 	=>  , // app模块的根目录
	 *  'conf_root' =>  , // 配置文件的根目录
	 * )</code>
	 */
	static function init($conf, $pathroot)
	{
		$mods = $conf['mod'];
		$servers = $conf['servers'];
		foreach($mods as $mod=>$cfg) {
			if (!empty($cfg['server'])) {
				$cfg['server'] = $servers[$cfg['server']];
			}
			self::$configure[$mod] = $cfg;
		}
		if (!empty($conf['autodsl_root'])) {
			self::$gendata = true;
			self::$gendslpath = $conf['autodsl_root'];
			require_once __DIR__.'/DataGenerator.php';
		}
		if (!empty($conf['encoding'])) {
			$encoding = strtoupper($conf['encoding']);
			self::$encoding = str_replace('-', '', $encoding);
		}

		self::$apipath = $pathroot['api_root'];
		self::$confpath = $pathroot['conf_root'];
	}

	/**
	 * 设置全局的拦截器，针对每个模块有效 
	 * 
	 * @param mixed $intercepters 
	 * @static
	 * @access public
	 * @return void
	 */
	static function setGlobalIntercepters(Array $intercepters)
	{
		self::$globalIntercepters = $intercepters;
	}

	/**
	 * 获取模块
	 * @param string $mod 模块名
	 * @param array $param 初始化参数
	 * @return Api
	 */
	static function get($mod, $param=array())
	{
		if (isset(self::$proxies[$mod])) {
			//支持注册一个Proxy来接口的伪实现
			//自动化测试的时候可以用到
			$proxy = self::$proxies[$mod];
		} else {
			if (!($proxy = self::getProxyFromConf($mod, $param)) ) {
				//默认按照php来实现
				require_once __DIR__.'/PHPProxy.php';
				$proxy = new PHPProxy($mod);
				$proxy->init(array(
					'conf_path'	=> self::$confpath,
					'api_path'	=> self::$apipath,
				),$param);
			}
			if ($proxy->cacheable()) {
				self::registerProxy($proxy);
			}
		}
		$api = new Api($proxy);
		foreach(self::$globalIntercepters as $intercepter) {
			$api->addIntercepter($intercepter);
		}
		return $api;
	}
	
	/**
	 * 从配置文件获取代理
	 * @param string $mod
	 * @param array $param
	 * @throws \Exception
	 * @return boolean|array
	 */
	private static function getProxyFromConf($mod, $param)
	{
		if (!isset(self::$configure[$mod])) {
			return false;
		}
		$conf = self::$configure[$mod];
		if (empty($conf['class'])) {
			throw new \Exception('Api.errconf mod='.$mod);
		}
		$internalmod = $mod;
		if (!empty($conf['mod'])) {
			//模块重命名
			$internalmod = $conf['mod'];
		}
		$class = $conf['class'];
		if (!empty($conf['server'])) {
			$options = $conf['server'];
		} else {
			$options = $conf;
		}
		unset($conf['class'], $conf['server'], $conf['mod']);
		$options = array_merge($options, $conf);
		require_once __DIR__."/$class.php";
		//为了支持多级模块，/转换成:
		$internalmod = str_replace('/', ':', $internalmod);
		$options['encoding'] = self::$encoding;
		$object = new $class($internalmod);
		$object->init($options, $param);
		return $object;
	}

	function __call($name,$args)
	{
		try {
			$this->callIntercepter('before', $name, $args);
			if (self::$gendata) {
				//上面把/转成了:，这里换回来
				$mod = str_replace(':', '/', $this->caller->getMod());
				$generator = new DataGenerator(self::$gendslpath, $mod, self::$encoding);
				$genret = $generator->genData($name);
				if ($genret) {
					$ret = $genret['data'];
				} else {
					$ret = $this->caller->call($name, $args);
				}
			} else {
				$ret = $this->caller->call($name, $args);
			}
			$this->callIntercepter('after', $name, $args, $ret);
			return $ret;
		}catch(\Exception $ex) {
			$this->callIntercepter('exception', $name, $args);
			throw $ex;
		}
	}

	private function callIntercepter($method, $callName,$args,$ret=null)
	{
		$intercepters = $this->intercepters;
		foreach($intercepters as $intercepter) {
			if ($method == 'after') {
				$intercepter->$method($this->caller, $callName, $args, $ret);
			} else {
				$intercepter->$method($this->caller, $callName, $args);
			}
		}
	}

	/**
	 * 对每一个接口函数启用事务
	 * 仅针对本地的PHP调用有效
	 * 需要使用Db库
	 */
	function enableTransaction()
	{
		require_once FR_ROOT.'db/TxIntercepter.php';
		$this->addIntercepter(new TxIntercepter());
	}

	/**
	 * 添加一个拦截器。类型名称相同的会被覆盖。
	 * @return void
	 */ 
	function addIntercepter(IIntercepter $intercepter)
	{
		$classname = get_class($intercepter);
		$this->intercepters[$classname] = $intercepter;
	}

	/**
	 * 删除一个拦截器。删除时会根据传入对象的类型名称来判断。
	 * 如果类型相同的拦截器会被删除。
	 * @return void
	 */ 
	function removeIntercepter(IIntercepter $intercepter)
	{
		$classname = get_class($intercepter);
		if (isset($this->intercepters[$classname])) {
			if ($intercepter == $this->intercepters[$classname]) {
				unset($this->intercepters[$classname]);
			}
		}
	}

	/**
	 * 获取所有拦截器
	 * @return array
	 */
	function getIntercepters()
	{
		return $this->intercepters;
	}

	/**
	 * 注册一个代理
	 * @param IProxy $proxy
	 */
	static function registerProxy(IProxy $proxy)
	{
		$mod = $proxy->getMod();
		self::$proxies[$mod] = $proxy;
	}
}