<?php

namespace lessp\lib\docs;

/**
 *  
 * @filesource  DocsController.php
 * @author      ronnie<comdeng@live.com>
 * @since       2014-12-21
 * @version     1.0
 * @copyright   Copyright (C) cc.lessp 2014 All rights reserved.
 * @desc		文档控制器
 */

use \lessp\fr\http\Controller;
use \lessp\fr\conf\Conf;
use lessp\fr\util\Exception;
use lessp\fr\api\Api;

class DocsController extends Controller
{
	function _before($method, $args)
	{
		Conf::set('lessp.view.root', '');
		
		// 去除访问的入侵漏洞
		if (strpos($this->get('url'), '.') !== FALSE) {
			throw Exception::notfound();
		}
		
		parent::_before($method, $args);
	}
	
	
	function index_action()
	{
		$this->navs();
		
		$this->setView(__DIR__.'/tpl/index.phtml');
	}
	
	function navs()
	{
		$navs = array();
		$this->search_dir(API_ROOT, '', $navs);
		$this->set('navs', $navs);
	}
	
	function search_dir($root, $url = '', &$navs = array())
	{
		$dir = $root.$url;
		$dh = opendir($dir);
		while ($file = readdir($dh)) {
			if ($file[0] == '.') {
				continue;
			}
				
			if (strpos($file, 'Export.php') > 0) {
				$navs['name'] = $url;
				$navs['file'] = $dir.$url;
			} else if (is_dir($dir.'/'. $file)) {
				$navs['subs'][] = array();
				$this->search_dir($root, $url.'/'.$file, $navs['subs'][count($navs['subs']) - 1]);
			}
		}
		closedir($dh);
	}
	
	/**
	 * 接口访问
	 */
	function api_action()
	{
		$this->navs();
		
		$url = $this->get('url');
		$this->set('title', '接口：'.$url);
		$this->set('url', $url);
		
		
		$url = trim($this->get('url'), '/');
		$arr = explode('/', $url);
		$last = $arr[count($arr) - 1];
		$file = sprintf('%s%s/%sExport.php', API_ROOT, $url, ucfirst($last));
		$className = Conf::get('lessp.ns').'api\\'.implode('\\', $arr).'\\'.ucfirst($last).'Export';
		require_once $file;
		
		require_once __DIR__.'/ClassAnalyticer.php';
		$ca = new ClassAnalyticer($file, array('clzName' => $className, 'docFirst' => false));
		$results = $ca->analytic();
		$this->set('results', $results);
		
		$this->setView(__DIR__.'/tpl/class.phtml');
	}
	
	/**
	 * 表单
	 */
	function form_action()
	{
		$url = $this->get('url');
		$method = $this->get('method');
		
		if (!$method || !$url) {
			throw Exception::args();
		}
		
		$this->set('title', '测试：'.$url);
		$this->set('url', $url);
		
		$url = trim($this->get('url'), '/');
		$arr = explode('/', $url);
		$last = $arr[count($arr) - 1];
		$file = sprintf('%s%s/%sExport.php', API_ROOT, $url, ucfirst($last));
		$className = Conf::get('lessp.ns').'api\\'.implode('\\', $arr).'\\'.ucfirst($last).'Export';
		
		if (!is_readable($file)) {
			throw Exception::notfound(array('file' => $file));
		}
		require_once $file;
		$rc = new \ReflectionClass($className);
		if (!$rc->hasMethod($method)) {
			throw Exception::notfound(array('method' => $method));
		}
		$m = $rc->getMethod($method);
		require_once __DIR__.'/ClassAnalyticer.php';
		$result = 	ClassAnalyticer::anaMethod($m, FALSE);
		if (!$result) {
			throw Exception::notfound();
		}
		
		$this->set('method', $result);
		
		$this->setView(__DIR__.'/tpl/form.phtml');
	}
	
	/**
	 * 提交调试的数据和返回结果
	 */
	function _debug_action()
	{
		$data = $this->gets('param', 'url', 'method');
		$url =  trim($data['url'], '/');
		$method = $data['method'] = trim($data['method']);
		
		// 格式化参数
		if ( isset($data['param']) ) {
			foreach ( $data['param'] as $_key => $value ) {
				if ( is_array($value) ) {
					$key = '';
					$values = array();
					while ( ($item = array_shift($value)) !== NULL ) {
						if ( $key === NULL || $key === '' ) {
							if ( isset($item['key']) ) {
								$key = $item['key'];
							}
						} else {
							if ( isset($item['value']) ) {
								if ( isset($values[$key]) ) {
									if ( ! is_array($values[$key]) ) {
										$values[$key] = array(
											$values[$key]
										);
									}
									$values[$key][] = $item['value'];
								} else {
									$values[$key] = $item['value'];
								}
								$key = '';
							} else
								if ( isset($item['key']) ) {
									$key = $item['key'];
								}
						}
					}
					$data['param'][$_key] = $values;
				}
			}
		} else {
			$data['param'] = array();
		}
		
		
		$api = Api::get($url);
		
		$output = array(
			'inputs' => $data,
		);
		
		list( $s_usec, $s_sec ) = explode(' ', microtime());
		try {
			$_out = call_user_func_array(array($api, $method), $data['param']);
			
			$output['outputs']['err'] = 'ok';
			$output['outputs']['data'] = $_out;
		} catch(\Exception $ex) {
			$output['outputs']['err'] = $ex->getMessage();
			$output['outputs']['data'] = $ex->getTraceAsString();
		}
		list( $e_usec, $e_sec ) = explode(' ', microtime());
		$output['inputs']['cost'] = sprintf('%.3fms', (($e_sec - $s_sec) + ($e_usec - $s_usec)) * 1000);
		
		$output['inputs']['stop'] = date('Y/m/d H:i:s', $e_sec) . '.' .	substr($e_usec, 2);
		$output['inputs']['start'] = date('Y/m/d H:i:s', $s_sec) . '.' . substr($s_usec, 2);
		
		$this->rmset();
		$this->set('output', $output);
	}
}