<?php
/**
* @description 数据迁移
* @author liweiwei
* @version 2016-11-8上午10:18:48
*/

/**
 * 活动2.0上线，数据迁移类
 * @author liweiwei
 * 大概流程
 * 1.旧库activity表数据抓取
 * 2.数据处理，分配到新库ac_activity和ac_ext表
 * 3.旧库apply表数据抓取
 * 4.数据处理，分配到新库ac_apply表
 * 5.注意：新表的自增id一定要大于旧库的；记录每次处理的旧库的节点，可以轮训处理，不用一下子都处理
 */
class ExportDbController extends Controller
{
    public $perpage = 1000;
    
    public function indexAction()
    {
        $url = "http://".$_SERVER['HTTP_HOST']."/crontab/exportdb/";
        $arr = array(
            $url.'exportacinfo',
            $url.'exportacapply',
            $url.'fillacuid',
            $url.'fillapplyuid',
            $url.'exportpoi',
            $url.'exportop',
            $url.'datacheck',
            $url.'showaclist',
        );
        
        echo "<br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;导入基本信息及扩展信息脚本(重复执行会覆盖已有的)(已关闭)：<a href='{$arr[0]}' target='_blank'>{$arr[0]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;导入报名信息脚本(重复执行会覆盖已有的)(已关闭)：<a href='{$arr[1]}' target='_blank'>{$arr[1]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;更新活动表uid的脚本(重复执行时值处理uid=0的)(已关闭)：<a href='{$arr[2]}' target='_blank'>{$arr[2]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;更新报名表uid的脚本(重复执行时值处理uid=0的)(已关闭)：<a href='{$arr[3]}' target='_blank'>{$arr[3]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;经纬度植入mongo的脚本(重复执行时，已插入的不会重复插入)(已关闭)：<a href='{$arr[4]}' target='_blank'>{$arr[4]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;运营后台操作记录的脚本(已关闭)：<a href='{$arr[5]}' target='_blank'>{$arr[5]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;数据完整性(已关闭)：<a href='{$arr[6]}' target='_blank'>{$arr[6]}</a><br/><br/>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;浏览旧数据：<a href='{$arr[7]}' target='_blank'>{$arr[7]}</a><br/><br/>";
    }
    
    /**
     * 获取远程图片的宽高和体积大小
     *
     * @param string $url 远程图片的链接
     * @param string $type 获取远程图片资源的方式, 默认为 curl 可选 fread
     * @param boolean $isGetFilesize 是否获取远程图片的体积大小, 默认false不获取, 设置为 true 时 $type 将强制为 fread
     * @return false|array
     */
    public function getSize($url, $type = 'curl', $isGetFilesize = false)
    {
        // 若需要获取图片体积大小则默认使用 fread 方式
        $type = $isGetFilesize ? 'fread' : $type;
         
        if ($type == 'fread') {
            // 或者使用 socket 二进制方式读取, 需要获取图片体积大小最好使用此方法
            $handle = fopen($url, 'rb');
             
            if (! $handle) return false;
             
            // 只取头部固定长度168字节数据
            $dataBlock = fread($handle, 168);
        }
        else {
            // 据说 CURL 能缓存DNS 效率比 socket 高
            $ch = curl_init($url);
            // 超时设置
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            // 取前面 168 个字符 通过四张测试图读取宽高结果都没有问题,若获取不到数据可适当加大数值
            curl_setopt($ch, CURLOPT_RANGE, '0-167');
            // 跟踪301跳转
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            // 返回结果
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
             
            $dataBlock = curl_exec($ch);
             
            curl_close($ch);
             
            if (! $dataBlock) return false;
        }
         
        // 将读取的图片信息转化为图片路径并获取图片信息,经测试,这里的转化设置 jpeg 对获取png,gif的信息没有影响,无须分别设置
        // 有些图片虽然可以在浏览器查看但实际已被损坏可能无法解析信息
        $size = getimagesize('data://image/jpeg;base64,'. base64_encode($dataBlock));
        if (empty($size)) {
            return false;
        }
         
        $result['width'] = $size[0];
        $result['height'] = $size[1];
         
        // 是否获取图片体积大小
        if ($isGetFilesize) {
            // 获取文件数据流信息
            $meta = stream_get_meta_data($handle);
            // nginx 的信息保存在 headers 里，apache 则直接在 wrapper_data
            $dataInfo = isset($meta['wrapper_data']['headers']) ? $meta['wrapper_data']['headers'] : $meta['wrapper_data'];
             
            foreach ($dataInfo as $va) {
                if ( preg_match('/length/iU', $va)) {
                    $ts = explode(':', $va);
                    $result['size'] = trim(array_pop($ts));
                    break;
                }
            }
        }
         
        if ($type == 'fread') fclose($handle);
         
        return $result;
    }
     
    
    /**
     * 活动基本信息及扩展信息数据迁移
     */
    public function exportAcInfoAction()
    {
        
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
        
        $exportdbModel = new ExportDbModel();
        
        $list = $exportdbModel->getAcInfoList();
        if (!$list) {
            exit('没有要处理的活动');
        }
        
//         $oldImgArr = array(
//                 'food.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FpJ2vhq7r-WVSSPVoXs3S_OLmqCF', 'size'=>'227991'),
//                 'interest.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FsazEn0yUa-SSLHXnqolMLerMXq8', 'size'=>'203792'),
//                 'outdoors.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FkhkNO3fZPDH4gay6C-vZJpepQwq', 'size'=>'290603'),
//                 'meeting.jpg'=>array('url'=>'http://apr.qiniu.toon.mobi/FjfFlnv-V-OnALZQ2ESExIZfVbqp', 'size'=>'187718'),
//                 'exhibition.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FgwD0k-P2d66xsAc3DqO-69NaWeF', 'size'=>'233478'),
//                 'performance.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FsyIDQ0GhlKwHbj6ctZDFWiUCd1q', 'size'=>'190061'),
//                 'meeting.png'=>array('url'=>'http://apr.qiniu.toon.mobi/FjfFlnv-V-OnALZQ2ESExIZfVbqp', 'size'=>'187718'),
//         );
        
       //  活动类型 0娱乐 1兴趣  2户外   3展览  4演出  5会议 6运动 7-沙龙
        $typeImg = array(
                0=>array('url'=>'http://apr.qiniu.toon.mobi/FpJ2vhq7r-WVSSPVoXs3S_OLmqCF', 'width'=>1125, 'height'=>474, 'size'=>'227991'),
                1=>array('url'=>'http://apr.qiniu.toon.mobi/FsazEn0yUa-SSLHXnqolMLerMXq8', 'width'=>1125, 'height'=>474, 'size'=>'203792'),
                2=>array('url'=>'http://apr.qiniu.toon.mobi/FkhkNO3fZPDH4gay6C-vZJpepQwq', 'width'=>1125, 'height'=>474, 'size'=>'290603'),
                5=>array('url'=>'http://apr.qiniu.toon.mobi/FjfFlnv-V-OnALZQ2ESExIZfVbqp', 'width'=>1125, 'height'=>474, 'size'=>'187718'),
                3=>array('url'=>'http://apr.qiniu.toon.mobi/FgwD0k-P2d66xsAc3DqO-69NaWeF', 'width'=>1125, 'height'=>474, 'size'=>'233478'),
                4=>array('url'=>'http://apr.qiniu.toon.mobi/FsyIDQ0GhlKwHbj6ctZDFWiUCd1q', 'width'=>1125, 'height'=>474, 'size'=>'190061'),
                5=>array('url'=>'http://apr.qiniu.toon.mobi/FjfFlnv-V-OnALZQ2ESExIZfVbqp', 'width'=>1125, 'height'=>474, 'size'=>'187718'),
        );
        
        $num = 0;
        foreach ($list as $oldInfo) {
            $imgInfo = $typeImg[$oldInfo['type']];
            
            $tmpAcInfo = array(
                    'id'                 => $oldInfo['id'], 
                    'uuid'               => Fn::getUuid(),
                    'title'              => $oldInfo['title'], 
                    'img'                => json_encode($imgInfo),
                    'type'               => $oldInfo['type'],
                    'start_time'         => $oldInfo['s_tm'],
                    'end_time'           => $oldInfo['s_tm']+7*24*3600,
                    'locate'             => $oldInfo['locate'],
                    'allow_apply'        => $oldInfo['checktype'] == 2 ? 0 : 1,
                    'price'              => $oldInfo['price'],
                    'checktype'          => $oldInfo['checktype'] == 1 ? 1 : 0,
                    'max'                => $oldInfo['max'],
                    'apply_end_time'     => $oldInfo['e_tm'],
                    'need_checkin'       => 0,
                    'checkin_start_time' => 0,
                    'checkin_end_time'   => 0,
                    'flag'               => $oldInfo['flag'],
                    'status'             => 1,
                    'c_fid'              => $oldInfo['c_fid'],
                    'fid'                => $oldInfo['fid'],
                    'uid'                => 0,
                    'isgroup'            => $oldInfo['isgroup'],
                    'create_time'        => $oldInfo['tm'],
                    'u_no'               => $oldInfo['u_no'],
                    'ord'                => $oldInfo['ord'],
                    'publicity'          => 1,
            );
            
            $tmpAcExtInfo = array(
                    'ac_id'        => $oldInfo['id'],
                    'longtitude'   => $oldInfo['longitude'],
                    'latitude'     => $oldInfo['latitude'],
                    'custom_field' => '',
                    'description'  => $oldInfo['description'],
                    'images'       => ''
            );
            // 如果是可以报名的，那么默认填充上姓名手机号
            if ($tmpAcInfo['allow_apply'] == 1) {
                $tmpAcExtInfo['custom_field'] = json_encode(array(array('key'=>'姓名', 'value'=>'姓名', 'id'=>1),array('key'=>'手机号', 'value'=>'手机号', 'id'=>2)));
            }
            $acRet = $exportdbModel->addAcInfo($tmpAcInfo);
            $extRet= $exportdbModel->addAcExt($tmpAcExtInfo);
//             $monRet = $exportdbModel->addPoiToMongo($oldInfo['id'], array('longtitude'=>$oldInfo['longitude'], 'latitude'=>$oldInfo['latitude']));
            
            if ($acRet && $extRet) {
                $num++;
            } else {
                echo "addAc:$acRet addExt:$extRet acId:{$oldInfo['id']} \n";
            }
            
        }
        exit( "本次处理了{$num}条记录，旧数据有".count($list)."条记录");
    }
    
    /**
     * 报名记录数据迁移
     */
    public function exportAcApplyAction()
    {
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
        
        $exportdbModel = new ExportDbModel();
        
        $page    = 1;
        $perpage = $this->perpage;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $exportdbModel->getAcApplyList($limit);
        if (!$list) {
            exit('没有要处理的信息');
        }
        
        $num = 0;
        while (!empty($list)) {
            foreach ($list as $oldInfo) {
                $tmpApplyInfo = array(
                        'id'             => $oldInfo['id'],
                        'ac_id'          => $oldInfo['a_id'],
                        'uid'            => 0,
                        'feed_id'        => $oldInfo['fid'],
                        'cus_info'       => '',
                        'content'        => $oldInfo['content'],
                        'verify_status'  => $oldInfo['flag'],
                        'checkin_status' => 0,
                        'checkin_time'   => 0,
                        'create_time'    => $oldInfo['tm'],
                        'status'         => 1,
                );
                $ret = $exportdbModel->addAcApply($tmpApplyInfo);
                if ($ret) {
                    $num++;
                } else {
                    echo "记录{$oldInfo['id']}失败  \n";
                }
            }
            echo "-----page {$page} 处理完毕\n";
            
            $page++;
            $limit = ($page-1)*$perpage.','.$perpage;
            $list = $exportdbModel->getAcApplyList($limit);
        }
        exit('处理完毕, 导入了'.$num."条记录\n");
    }
    
    /**
     * 活动新表，填充uid字段 
     */
    public function fillAcUidAction()
    {
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
        
        $exportdbModel = new ExportDbModel();
        
        $page    = 1;
        $perpage = $this->perpage;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $exportdbModel->getAcFromNew($limit);
        if (!$list) {
            exit('没有要处理的信息\n');
        }
        
        $num = 0;
        while (!empty($list)) {
            foreach ($list as $acInfo) {
                if (!empty($acInfo['uid']) || empty($acInfo['c_fid'])) {
                    continue;
                }
                if (in_array($acInfo['id'], array(226,276,282,284,446,611,623))) {
                    continue;
                }
                
                // 获取uid
                $feedInfo = Toon::getListFeedInfo([$acInfo['c_fid']], 'portal', $errMsg);
                if (empty($feedInfo) || !isset($feedInfo[0]['userId'])) {
                    continue;
                }
                
                $ret = $exportdbModel->updateAcUid($acInfo['id'], $feedInfo[0]['userId']);
                if ($ret) {
                    $num++;
                } else {
                    echo "记录{$acInfo['id']}失败\n";
                }
            }
            echo "-----page {$page} 处理完毕\n";
            $page++;
            $limit = ($page-1)*$perpage.','.$perpage;
            $list = $exportdbModel->getAcFromNew($limit);
        }
        
        exit('处理完毕, 导入了'.$num."条记录\n");
    }
    
    /**
     * 报名记录新表，填充uid字段
     */
    public function fillApplyUidAction()
    {
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
    
        $exportdbModel = new ExportDbModel();
    
        $page    = 1;
        $perpage = $this->perpage;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $exportdbModel->getApplyFromNew($limit);
        if (!$list) {
            exit("没有要处理的信息\n");
        }
    
        $num = 0;
        while (!empty($list)) {
            foreach ($list as $applyInfo) {
                if (!empty($applyInfo['uid']) || empty($applyInfo['feed_id'])) {
                    continue;
                }
                if (in_array($applyInfo['id'], array(366,496,499,511,530,571,582,640,686,687,689,702,703,771,927,953,963,1052,1482))) {
                    continue;
                }
                // 获取uid
                $feedInfo = Toon::getListFeedInfo([$applyInfo['feed_id']], 'portal', $errMsg);
                if (empty($feedInfo) || !isset($feedInfo[0]['userId'])) {
                    continue;
                }
    
                $ret = $exportdbModel->updateApplyUid($applyInfo['id'], $feedInfo[0]['userId']);
                if ($ret) {
                    $num++;
                } else {
                    echo "记录{$applyInfo['id']}失败\n";
                }
            }
            echo "-----page {$page} 处理完毕\n";
            $page++;
            $limit = ($page-1)*$perpage.','.$perpage;
            $list = $exportdbModel->getApplyFromNew($limit);
        }
    
        exit('处理完毕, 导入了'.$num."条记录\n");
    }
    
    /**
     * 活动基本信息及扩展信息数据迁移
     */
    public function exportPoiAction()
    {
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
    
        $exportdbModel = new ExportDbModel();
    
        $list = $exportdbModel->getAcInfoList();
        if (!$list) {
            exit("没有要处理的活动\n");
        }
        $num = 0;
        foreach ($list as $oldInfo) {
            $longLatInfo = array('longtitude'=>$oldInfo['longitude'], 'latitude'=>$oldInfo['latitude']);
            if (empty($oldInfo['longitude']) || empty($oldInfo['latitude'])) {
                continue;
            }
            $monRet = $exportdbModel->addPoiToMongo($oldInfo['id'], $longLatInfo);
            if ($monRet) {
                $num++;
            } else {
                echo "failed-monRet:".(int)$monRet." acId:{$oldInfo['id']} longitude:{$oldInfo['longitude']} latitude:{$oldInfo['latitude']}"."\n";
            }
        }
        
        exit("本次处理了{$num}条记录，旧数据有".count($list)."条记录\n");
    }
    
    
    /**
     * 运营后台操作记录
     */
    public function exportOpAction()
    {
        exit('Has been executed successfully.');
        
        header("Content-Type:text/html;charset=utf-8");
        set_time_limit(0);
    
        $exportdbModel = new ExportDbModel();
    
        $list = $exportdbModel->getOpList();
        if (!$list) {
            exit("没有要处理的活动\n");
        }
        $num = 0;
        foreach ($list as $oldInfo) {
            $tmpInfo = array(
                    'ac_id'       => $oldInfo['aid'],
                    'staff'       => $oldInfo['staff'],
                    'state'       => $oldInfo['state'],
                    'create_time' => $oldInfo['tm'],
            );
            $ret = $exportdbModel->addOp($tmpInfo);
    
            if ($ret) {
                $num++;
            } else {
                echo "fail-ret:$ret acId:{$oldInfo['aid']} \n";
            }
        }
        exit( "本次处理了{$num}条记录，旧数据有".count($list)."条记录\n");
    }
    
    /**
     * 验证数据完整性
     */
    public function dataCheckAction()
    {
        exit('Has been executed successfully.');
        
        // 活动表新旧表数量
        // 活动报名表新旧表数量
        // 运营后台操作记录
        $exportdbModel = new ExportDbModel();
        $r = $exportdbModel->getStat();
        
        Fn::p($r);
    }
    
    public function showAcListAction()
    {
        $exportdbModel = new ExportDbModel();
        
        $page    = 1;
        $perpage = $this->perpage;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $exportdbModel->getAcFromNew($limit);
        
        $endArr = $ingArr = $startArr = array();
        foreach ($list as $k=>$v) {
            if (time() > $v['end_time']) {
                $endArr[] = $v;
            } else if (time() < $v['start_time']) {
                $startArr[] = $v;
            } else {
                $ingArr[] = $v;
            }
        }
        foreach (array('ing'=>$ingArr, 'start'=>$startArr, 'end'=>$endArr) as $listtype => $list) {
            if ($listtype == 'ing') {
                echo "<h3>进行中(".count($ingArr).")</h3>";
            }
            if ($listtype == 'start') {
                echo "<h3>未开始(".count($startArr).")</h3>";
            }
            if ($listtype == 'end') {
                echo "<h3>已结束(".count($endArr).")</h3>";
            }
            echo "<hr><hr>";
            foreach ($list as $k=>$v) {
                echo "{$v['id']}-{$v['title']}";
                if (time() > $v['end_time']) {
                    echo "【已结束】";
                } else if (time() < $v['start_time']) {
                    echo "【未开始】";
                } else {
                    echo "【进行中】";
                }
                
                $link = "http://".$_SERVER['HTTP_HOST']."/html/src/index.html?entry=3&ac_id={$v['id']}&code=YlFadOmaJjrRJZlIknaoBVl8CpM2h7wAfPAJL5FeyZ+p5v7hUUKVS1bXku/rVUK6jEahzbrL4xViAtzMamUMOi+9odgAA+5/r17PpRCIDZbpkVFcvbYwVMVj/vcF1gA0bLb+mU/MR9E=#!/active-info?id={$v['id']}";
                echo "<a href='{$link}' target='_blank'>《点击查看》</a>";
                echo "<hr>";
            }
        }
    }
    
}