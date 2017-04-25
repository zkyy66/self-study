<?php

class AdminBaseController extends Controller
{
    public $_operatorInfo = [];
    
    public function init() {
        parent::init();
        
        if (APP_ENV == 'product') {
            if(!$this->auth()) {
                Fn::outputToJson(self::OK, '抱歉，您没有权限！');
            }
            $this->_operatorInfo  = json_decode(Fn::hexToStr($_COOKIE['FTUINFO']), true);
            if (empty($this->_operatorInfo['name']) || empty($this->_operatorInfo['id'])) {
                Fn::outputToJson(self::ERR_PARAM, '没有获取到用户信息');
            }
        } else {
            $this->_operatorInfo = ['name'=>'adminInTest', 'id'=>11];
        }
    }
    
    /**
     * 后台验证
     * @return boolean
     */
    private function auth()
    {
        return true;
        
        $platfromName = 'activityManage';
        $permission   = '/activityManage/admin/html';
        $data   = [
            'permissionType' => 'URL',
            'platformName'   => $platfromName,
            'permission'     => $permission,
            'addActionLog'   => true,
            'requestParams'  => json_encode([]),
            '-'              => time()
        ];
        $params = [];
        foreach( $data as $key => $val ) {
            $params[] = $key.'='.$val;
        }
        $pstr = implode( '&', $params );
        
        $integrateId = isset( $_COOKIE['FTSID'] ) ? $_COOKIE['FTSID'] : '';
        if (!$integrateId) {
            Fn::writeLog('运营后台权限验证： $_COOKIE[FTSID]无值');
            return false;
        }
        
        $authUrl = Yaf_Registry::get('config')->get('site.info.auth_url');
        if (!$authUrl) {
            Fn::writeLog('运营后台权限验证：config中没有设置site.info.auth_url');
            return false;
        }
        
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $pstr); //设置POST提交的字符串
        curl_setopt($ch, CURLOPT_COOKIE, 'IntegrateId='.$integrateId );
    
        $result = curl_exec($ch);
//         $info   = curl_getinfo( $ch ); //得到返回信息的特性
        curl_close($ch);
        $res = json_decode($result, true);
        if( $res['success'] ) {
            return true;
        }
        
        Fn::writeLog('运营后台权限验证：auth_url验证失败：'.json_encode($res));
        return false;
    }
}