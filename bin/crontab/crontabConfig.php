<?php
/**
 * 定时运行程序的主程序配置文件
 * @author liweiwei
 */

// # check end time and down wall
// */1 * * * * /usr/local/php/bin/php /home/work/p100.signon.systoon.com/public/cli.php "request_uri=/crontab/changewalltype/checkendtime" >> /home/logs/ac_cron.log 2>&1

// # up down wall
// */1 * * * * /usr/local/php/bin/php /home/work/p100.signon.systoon.com/public/cli.php "request_uri=/crontab/changewalltype/task" >> /home/logs/ac_cron_task.log 2>&1

// #send notice
// */1 * * * * /usr/local/php/bin/php /home/work/p100.signon.systoon.com/public/cli.php "request_uri=/crontab/sendnotice/get" >> /home/logs/ac_cron_notice.log 2>&1

// #checkfeed-acinfo
// 0 * * * * /usr/local/php/bin/php /home/work/p100.signon.systoon.com/public/cli.php "request_uri=/crontab/checkfeed/index"  >> /home/logs/ac_cron_checkfeed_acinfo.log 2>&1

// #checkfeed-apply
// 0 * * * * /usr/local/php/bin/php /home/work/p100.signon.systoon.com/public/cli.php "request_uri=/crontab/checkfeed/apply"  >> /home/logs/ac_cron_checkfeed_apply.log 2>&

$crontabConfig = array(
	'now'	=> array( //
// 		'checkendtime' => array( // 定时将已上墙并且已结束的活动下墙
// 			     'ip' => '172.28.28.199',
// 			     'limit' => 1, //每分钟运行一次
// 			     'beginTm' => strtotime('2016-11-18 00:00:00'),
// 			     'endTm' => strtotime('2020-01-01 23:59:59'),
//         'requestUri' => '/crontab/changewalltype/checkendtime',
// 		),
// 		'task_onoffwall'=>array( // 定时将运营后台设置的定时上下墙的任务执行
// 		      'ip' => '172.28.28.199',
// 		      'limit' => 1, //每分钟运行一次
// 		      'beginTm' => strtotime('2016-11-18 00:00:00'),
// 		      'endTm' => strtotime('2020-01-01 23:59:59'),
// 		      'requestUri' => '/crontab/changewalltype/task',
// 		),
		'sendnotice'=>array( // 定时发通知
	        'ip' => '172.28.50.174',
	        'limit' => 1, //每分钟运行一次
        	'beginTm' => strtotime('2017-01-01 00:00:00'),
        	'endTm' => strtotime('2027-01-01 23:59:59'),
	        'requestUri' => '/crontab/sendnotice/get',
		),
		// 'checkfeed_acinfo'=>array( // 定时检查活动发布者的feed信息，不存在的将活动状态置为2.
	 //        'ip' => '172.28.50.174',
	 //        'limit' => 60, //每分钟运行一次
	 //        'beginTm' => strtotime('2016-11-18 00:00:00'),
	 //        'endTm' => strtotime('2020-01-01 23:59:59'),
	 //        'requestUri' => '/crontab/checkfeed/index',
		// ),
//		'checkfeed_apply'=>array( // 定时检查活动报名者的feed信息，不存在的将报名状态置为2.
//	        'ip' => '172.28.50.174',
//	        'limit' => 60, //每分钟运行一次
//        	'beginTm' => strtotime('2017-01-01 00:00:00'),
//        	'endTm' => strtotime('2027-01-01 23:59:59'),
//	        'requestUri' => '/crontab/checkfeed/apply',
//		),
    'activity_keep_month' => array(//每分钟查询活动已结束数据，结束时间大于一个月则进行伪删除
        'ip' => '172.28.50.174',
        'limit' => 1, //每分钟运行一次
        'requestUri' => '/crontab/changewalltype/keeptime',
    ),
    // 定时将已推荐的已结束的活动取消推荐状态
    'removeRecommendFlag'=>array(
        'ip' => '172.28.50.174',
        'limit' => 1, //每分钟运行一次
        'beginTm' => strtotime('2017-01-01 00:00:00'),
        'endTm' => strtotime('2027-01-01 23:59:59'),
        'requestUri' => '/crontab/changewalltype/removeRecommendFlag',
    ),
    // 活动签到提醒，距离现在时间还有1小时开始的
//    'sendCheckinNotice'=>array(
//        'ip' => '172.28.50.174',
//        'limit' => 1, //每分钟运行一次
//        'beginTm' => strtotime('2017-01-01 00:00:00'),
//        'endTm' => strtotime('2027-01-01 23:59:59'),
//        'requestUri' => '/crontab/sendnotice/sendCheckinNotice',
//    ),
    // 活动开始提醒，距离现在时间还有1小时开始的
    'sendStartNotice'=>array(
        'ip' => '172.28.50.174',
        'limit' => 1, //每分钟运行一次
        'beginTm' => strtotime('2017-01-01 00:00:00'),
        'endTm' => strtotime('2027-01-01 23:59:59'),
        'requestUri' => '/crontab/sendnotice/sendStartNotice',
    ),
	        
//         'activity_send_notice' => array(//每分钟查询活动状态为1且未开始的活动，在活动开始时间/签到时间前一小时发送通知告知报名者
//             'ip' => '172.28.28.199',
//             'limit' => 1, //每分钟运行一次
//             'requestUri' => '/crontab/sendnotice/checksendnotice',
            
//         )
	    
	),
	'day' => array(
// 		'dayTest' => array(
// 			'ip' => '172.28.50.174',
// 			'beginTm' => strtotime('2016-01-01 00:00:00'),
// 			'endTm' => strtotime('2020-12-31 23:59:59'),
// 			'time' => array('03:00:00', '05:00:00'),
// 			'requestUri' => '/crontab/test/day',	
// 		),
// 	    'talentProduce' => array(
// 	        'ip' => '172.28.50.174',
// 	        'beginTm' => strtotime('2016-01-01 00:00:00'),
// 	        'endTm' => strtotime('2020-12-31 23:59:59'),
// 	        'time' => array('03:00:00'),
// 	        'requestUri' => '/crontab/talent/produce',
// 	    ),
	       
	    
	),
	'week' => array(
// 		'weekTest' => array(
// 			'ip' => '172.28.50.174',
// 			'limit' => '4', //星期几
// 			'beginTm' => strtotime('2016-01-01 00:00:00'),
// 			'endTm' => strtotime('2020-12-31 23:59:59'),
// 			'time' => array('03:00:00'),
// 			'requestUri' => '/crontab/test/week',	
// 		),		
	),
    
);