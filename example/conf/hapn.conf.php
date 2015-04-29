<?php
Conf::set('hapn.debug', 'manual');
// Conf::set('hapn.debug', true);
// 记录代码覆盖率日志
// 日志文件位置在tmp/cov下
Conf::set('hapn.logcov', false); //
Conf::set('hapn.log.file', 'hapn');
Conf::set('hapn.log.roll', 2);
Conf::set('hapn.log.level', 8);
Conf::set('hapn.log.request', 'request.log');
Conf::set('hapn.view', 'PhpView');
Conf::set('hapn.encodeinput', true);

Conf::set('hapn.error.redirect', 
		array( 
			'hapn.error' => '!/_private/error',
			'hapn.u_notfound' => '!/_private/notfound',
			'hapn.u_login' => '/user/login?tpl=cc&tpl_reg=cc&u=[url]',
			'hapn.u_power' => '!/_private/power'
		));
Conf::set('hapn.error.retrycode', '/\.net_/');
Conf::set('hapn.error.retrymax', 2);
Conf::set('hapn.error.userreg', '/\.u_/');

// 实际单元测试时不应该加载此配置
// *
Conf::set('hapn.filter.init', array( 
	'TeamInitFilter'
));
Conf::set('hapn.filter.input', array(/*,'CSRFFilter'*/));
Conf::set('hapn.filter.clean', array());
// */

Conf::set('hapn.encoding', 'UTF-8');
// Conf::set('hapn.ie','GBK');
// Conf::set('hapn.oe','GBK');
//

Conf::set('db.conf', 
		array( 
			'text_db' => 'demo',
			'text_table' => 't_text',
			'text_compress_len' => 1,
			'max_text_len' => 65535,
			// 1是取模分表
			'splits' => array( 
				't_text' => array( 
					'text_id',
					array( 
						1 => 10
					)
				)
			),
			'log_func' => 'Logger::trace',
			'test_mode' => 0,
			'guid_db' => 't_team',
			'guid_table' => 'c_guid',
			'db_pool' => array( 
				'ip1' => array( 
					'ip' => '192.168.3.30',
					'user' => 'HapN',
					'pass' => 'HapN',
					'port' => 3308,
					'charset' => 'utf8'
				)
			),
			'dbs' => array( 
				't_team' => 'ip1',
				't_picture' => 'ip1'
			)
		));
Conf::set('db.readonly', false);

if ( defined('APP_MODE') && APP_MODE != 'api' ) {
	Conf::set('hapn.api.server', 
			array( 
				'host' => '0.0.0.0',
				'port' => 9500,
				'setting' => array( 
					'worker_num' => 8, // worker进程数量
					'daemonize' => false, // 守护进程设置成true
					'max_request' => 10000, // 最大请求次数，当请求大于它时，将会自动重启该worker
					'dispatch_mode' => 1
				)
			));
	
	Conf::set('api.servers', 
			array( 
				'apiserver' => array( 
					'isweb' => 1,
					'ignore_leading_crlf' => false,
					'curlopt' => array( 
						CURLOPT_TIMEOUT => 5,
						CURLOPT_CONNECTTIMEOUT => 1
					),
					'servers' => array( 
						'192.168.3.20:9500'
					)
				)
			));
	
	$apiServer = array( 
		'class' => 'HttpJsonProxy',
		'server' => 'apiserver'
	);
	
	Conf::set('api.mod', array( 
		'user' 		=> $apiServer,
		'func' 		=> $apiServer,
		'func/*' 	=> $apiServer,
		'team' 		=> $apiServer,
		'active' 	=> $apiServer,
		'process' 	=> $apiServer,
		'util'		=> $apiServer,
	));
}

// 默认curl的配置
Conf::set('curl.options', array( 
	// seconds
	CURLOPT_TIMEOUT => 5,
	CURLOPT_CONNECTTIMEOUT => 1
));

