<?php
/**
 *  
 * @file        BaseApp.php
 * @author      ronnie<comdeng@live.com>
 * @date        2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @description 
 */

use \lessp\fr\util\Timer;
use \lessp\fr\log\Logger;
use \lessp\fr\conf\Conf;
use \lessp\fr\api\Api;
use lessp\fr\db\TxScope;
use lessp\fr\db\Db;

const APP_DEBUG_ENABLE = true;
const APP_DEBUG_DISABLE = false;
const APP_DEBUG_MANUAL = 'manual';

const APP_MODE_WEB = 'web';
const APP_MODE_TOOL = 'tool';

abstract class BaseApp
{
	
	/**
	 * 是否允许调试，可选值 true/false/munual
	 * false: 不允许调试
	 * true： 允许调试
	 * munual： 手动，通过传入_d参数启动
	 * @var int
	 */
	public $debug;
	
	
	/**
	 * app的运行模式，可选值为 web/tool
	 * web: 网页模式
	 * tool:命令行模式
	 * @var string
	 */
	public $mode;
	
	/**
	 * 计时器
	 * @var lessp\fr\util\Timer
	 */
	public $timer;
	
	/**
	 * 编码
	 * @var string
	 */
	public $encoding = 'UTF-8';
	
	/**
	 * 结束状态
	 * @var string
	 */
	public $endStatus = 'init';
	
	/**
	 * app的ID
	 * @var int
	 */
	public $appId = 0;
	
	/**
	 * 站点的命名空间
	 * @var string
	 */
	public $ns;
	
	
	/**
	 * @ignore
	 */
	function __construct()
	{
		$this->internalInit();
		require_once FR_ROOT.'util/Timer.php';
		$this->timer = new Timer();
	}
	
	/**
	 * 内部初始化
	 * @ignore
	 */
	protected function internalInit()
	{
		$path = get_include_path();
		$path = LIB_ROOT.PATH_SEPARATOR.EXLIB_ROOT.PATH_SEPARATOR.$path;
		set_include_path($path);
	}
	
	/**
	 * 开始运行app
	 */
	function run()
	{
		$this->timer->begin('total', 'init');
		$this->init();
		$this->timer->end('init');
		
		$this->timer->begin('process');
		$this->process();
		$this->timer->end('process');
		
		$this->endStatus = 'ok';
	}
	
	/**
	 * 初始化
	 */
	function init()
	{
		require_once FR_ROOT.'conf/Conf.php';
		
		$this->appId = $this->genAppId();
		global $_LessP_appid;
		$_LessP_appid = $this->appId;
	
		$this->_initConf();
		$this->_initEnv();
		$this->_initLog();
		
		if (true !== Conf::get('lessp.disable_api')) {
			//没有强制关闭
			$this->_initApi();
		}
		
		if (true !== Conf::get('lessp.disable_db')) {
			//没有强制关闭
			$this->_initDB();
		}
	}
	
	/**
	 * 初始化配置信息
	 */
	protected function _initConf()
	{
		$confs = array(
			CONF_ROOT.'lessp.conf.php',
		);
		Conf::load($confs);
		$this->debug = Conf::get('lessp.debug',false);
		if ($this->mode === APP_MODE_WEB) {
			$this->debug === APP_DEBUG_MANUAL ? ($this->debug = !empty($_GET['_d']) ) : APP_DEBUG_DISABLE;
		} else {
			$args = getopt('d:');
			$this->debug === APP_DEBUG_MANUAL ? ($this->debug = !empty($args['d'])) : APP_DEBUG_DISABLE;
		}
		$this->encoding = strtoupper(Conf::get('lessp.encoding', $this->encoding));
		
		if ($this->debug) {
			ini_set('display_errors', 1);
		}
		
		$this->ns = Conf::get('lessp.ns', '\\lessp\\site\\');
	}
	
	/**
	 * 初始化环境变量
	 * @ignore
	 */
	protected function _initEnv()
	{
		mb_internal_encoding($this->encoding);
		iconv_set_encoding('internal_encoding', $this->encoding);
		
		// 使用很严格的错误报告机制
		error_reporting(E_ALL|E_NOTICE);
		
		// 设置默认的处理器
		set_error_handler(array($this, 'errorHandler'));
		set_exception_handler(array($this, 'exceptionHandler'));
		register_shutdown_function(array($this, 'shutdownHandler'));
	}
	
	/**
	 * 初始化日志
	 * 
	 * debug模式下log级别直接为debug
	 */
	private function _initLog()
	{
		require_once FR_ROOT.'log/Log.php';
		
		$logFile = Conf::get('lessp.log.file', 'LessP');
		$logLevel = Conf::get('lessp.log.level', $this->debug ? \lessp\fr\log\LOG_LEVEL_DEBUG : \lessp\fr\log\LOG_LEVEL_TRACE);
		if ($this->debug === APP_DEBUG_ENABLE) {
			//DEBUG模式下log级别直接为debug
			$logLevel = \lessp\fr\log\LOG_LEVEL_DEBUG;
		}
		$roll = Conf::get('lessp.log.roll', \lessp\fr\log\LOG_ROLLING_NONE);
		Logger::init(LOG_ROOT, $logFile, $logLevel, array(), $roll);
	
		$basic = array('logid' => $this->appId);
		Logger::addBasic($basic);
	}
	
	/**
	 * 初始化DB
	 */
	function _initDB()
	{
		require_once FR_ROOT.'db/Db.php';
		
		$conf = Conf::get('db.conf');
		$readonly = Conf::get('db.readonly',false);
		Db::init($conf);
		Db::setReadOnly(!!$readonly);
	}
	
	/**
	 * 生成app的唯一id
	 * @return int
	 */
	function genAppId()
	{
		$time = gettimeofday();
		$time = $time['sec'] * 100 + $time['usec'];
		$rand = mt_rand(1, $time << 1);
		$id = ($time ^ $rand)  & 0xFFFFFFFF;
		return floor($id / 100) * 100;
	}
	
	/**
	 * 初始化Api
	 */
	function _initApi()
	{
		$servers = Conf::get('Api.servers', array());
		if ($this->debug) {
			foreach($servers as $key=>$server) {
				//如果调试模式
				$servers[$key]['curlopt'][CURLOPT_VERBOSE] = true;
			}
		}
		$modmap = Conf::get('Api.mod', array());
		$autogen = Conf::get('Api.autodsl_root', array());
		
		require_once FR_ROOT.'api/Api.php';
		Api::init(
			array(
				'servers'		=> $servers,
				'mod'			=> $modmap,
				'encoding'		=> $this->encoding,
				'autodsl_root'	=> $autogen,
			), array(
				'api_root'		=> API_ROOT,
				'conf_root'		=> CONF_ROOT.'api/',
				'api_ns'		=> $this->ns.'api\\',
			)
		);
		$intercepterclasses = Conf::get('Api.intercepters', array());
		$intercepters = array();
		foreach($intercepterclasses as $class) {
			require_once PLUGIN_ROOT.'intercepters/'.$class.'.php';
			$class = $this->ns.'intercepter\\'.$class;
			$intercepters[] = new $class();
		}
		Api::setGlobalIntercepters($intercepters);
	}
	
	/**
	 * 是否为用户错误，默认使用\.u_作为用户错误的标识
	 * @param string $errcode
	 * @return boolean
	 */
	function isUserErr($errcode)
	{
		$usererr = Conf::get('lessp.error.userreg', '/\.u_/');
		return preg_match($usererr, $errcode) > 0;
	}
	
	/**
	 * 错误处理函数
	 * @return boolean 如果返回false，标准错误处理处理程序将会继续调用
	 */
	function errorHandler()
	{
		$error = func_get_args();
		restore_error_handler();
		if (!($error[0] & error_reporting())) {
			Logger::debug('caught info, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], $error[3]);
			set_error_handler(array($this,'errorHandler'));
			return false;
		} elseif ($error[0] === E_USER_NOTICE) {
			Logger::trace('caught trace, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], $error[3]);
			set_error_handler(array($this,'errorHandler'));
			return false;
		} elseif ($error[0] === E_USER_WARNING) {
			Logger::warning('caught warning, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], $error[3]);
			set_error_handler(array($this,'errorHandler'));
			return false;
		} elseif ($error[0] === E_USER_ERROR) {
			Logger::fatal('caught error, errno:%d,errmsg:%s,file:%s,line:%d', $error[0], $error[1], $error[2], $error[3]);
			set_error_handler(array($this,'errorHandler'));
			return false;
		} else {
			$errmsg = sprintf('caught error, errno:%d,errmsg:%s,file:%s,line:%d',$error[0], $error[1], $error[2], $error[3]);
			Logger::fatal($errmsg);
			$this->endStatus = 'error';
			return true;
		}
	}
	
	/**
	 * 异常处理函数
	 * @param Exception $ex
	 */
	function exceptionHandler($ex)
	{
		restore_exception_handler();
		$errcode = $ex->getMessage();
		$errmsg = sprintf('caught exception, errcode:%s, trace: %s', $errcode, $ex->__toString());
		if (($pos = strpos($errcode,' '))) {
			$errcode = substr($errcode,0,$pos);
		}
		$this->endStatus = $errcode;
		if ($this->isUserErr($errcode)){
			Logger::trace($errmsg);
		} else {
			Logger::fatal($errmsg);
			$trackConf = $usererr = Conf::get('lessp.log.tracking');
		}
	}
	
	/**
	 * 所有程序结束时调用的方法
	 */
	function shutdownHandler()
	{
		$result = $this->timer->getCosts();
		$str[] = '[time:';
		foreach($result as $key => $time) {
			$str[] = ' '.$key.'='.$time;
		}
		$str[] = ']';
		Logger::notice(implode('',$str).' status='.$this->endStatus);
		Logger::flush();
		
		if (true !== Conf::get('lessp.disable_db')) {
			//做一些清理
			require_once FR_ROOT.'db/TxScope.php';
			TxScope::rollbackAll();
			Db::close();
		}
	}
}