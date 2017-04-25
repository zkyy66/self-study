<?php
/**
 * 定时运行程序主程序
 */
require_once 'crontabConfig.php';

define('ROOT_DIR', dirname(dirname(dirname(__FILE__))));

//读取本服务器IP
$ipaddr = `/sbin/ifconfig -a|grep inet|grep -v 127.0.0.1|grep -v inet6|awk '{print $2}'|tr -d "addr:"`;
if (! $ipaddr) exit('获取IP失败');

$ipaddr = str_replace("\r", "", $ipaddr);
$ipaddr = str_replace("\n", "", $ipaddr);

$cliPhp = ROOT_DIR . '/public/cli.php';

$baseLogDir = '/home/logs/huodongCrontab/';

$nowTime 	= time();  //获取当前时间
$nowMinute	= date('i', $nowTime);

if ($nowMinute == 0) $nowMinute = 60;
//创建日志目录
if (! is_dir($baseLogDir)) {
    mkdir($baseLogDir, 0755, true);
}

foreach ($crontabConfig as $typeKey => $item) {
	//循环判断索要运行的bin程序
	//==============================start=====================================
	foreach ($item as $key => $data) {
		//组合各程序执行时间点
		$nowTAry = array();

		if ($typeKey == 'now') {
			if (isset($data['limit']) && $data['limit']) {
				$modNum = $nowMinute % $data['limit'];
				if ($modNum == 0) {
					$nowTAry[] = $nowTime;
				}
			}
			
		} elseif ($typeKey == 'day') {
			$y	= date('Y', $nowTime);
			$m  = date('m', $nowTime);
			$d  = date('d', $nowTime);
				
			//组合每天的执行时间点
			foreach ($data['time'] as $setTime) {
				$nowTAry[] = strtotime($y.'-'.$m.'-'.$d .' '.$setTime);
			}
			
		} elseif ($typeKey == 'week') {
			$week = date('N', $nowTime); //当前星期几
				
			//计算每星期$data[limit]执行日期
			$noWe = strtotime('+'.$data['limit']-$week.' day');
			$y	  = date('Y', $noWe);
			$m    = date('m', $noWe);
			$d    = date('d', $noWe);
			//组合每天的执行时间点
			foreach ($data['time'] as $setTime) {
				$nowTAry[] = strtotime($y.'-'.$m.'-'.$d .' '.$setTime);
			}
			
		} elseif ($typeKey == 'month') {
			$y	= date('Y', $nowTime);
			$m  = date('m', $nowTime);
				
			//组合每月的执行时间点
			foreach ($data['time'] as $setTime) {
				foreach ($data['limit'] as $day) {
					$nowTAry[] = strtotime($y.'-'.$m.'-'.$day.' '.$setTime);
				}
			}
		}
		

		//==============================end=====================================
		if (! $nowTAry && $typeKey != 'now') {
			echo 'error time R_msg ' . date('Y-m-d H:i:s', $nowTime) . '--' . $key . '--' . $typeKey."\n";
			continue;
		} else {
			//判断执行时间是否过期和执行时间点
			foreach ($nowTAry as $nowT) {
				if ((! isset($data['endTime']) || ($nowTime <= $data['endTime']+30)) &&
				    (! isset($data['beginTime']) || ($data['beginTime'] <= $nowTime)) &&
				    ($nowT <= $nowTime+30) && ($nowT > $nowTime-30)) {
					//判断程序执行机器
					if ($ipaddr != $data['ip']) {
						continue;
					} else {
						echo $data['requestUri'] . "\n";
						$logFile = $baseLogDir . "crontab_{$key}_".date('Ym').".log";
						$command = "nohup /usr/bin/php  {$cliPhp} request_uri=\"{$data['requestUri']}\" >> {$logFile} 2>&1 &";
						exec($command);
					}
				}
			}
		}
	}
}