<?php

if (!defined('IN_IA')) {
	exit('Access Denied');
}

class Index_EweiShopV2Page extends SystemPage {

	function main() {

		global $_W,$_GPC;
		if(empty($_GPC['op'])) {
			$_GPC['op'] = 'display';
		}
		$hosturl = urlencode($_SERVER['HTTP_HOST']);
		$updatehost = base64_decode('aHR0cDovL3ZpcC56aGlmdW4uY2MvYXBpL3RpbWUucGhw');
		if($_GPC['op'] == 'display'){
			$op = $_GPC['op'];	
			$gettimeurl = $updatehost . '?a=client_check_time&v=' . $ver . '&u=' . $hosturl;
			$domain_time = file_get_contents($gettimeurl);	
			$chosturl = $updatehost . '?a=chanage&v=' . $ver. '&u=' . $hosturl;		
			$cinfo = file_get_contents($chosturl);
		}
	    $time = date( strtotime('+7 days'));
		if($domain_time <= $time){
            $domain_time = base64_decode('5o6I5p2D5pe26Ze05bey5Yiw5pyfLOivt+mHjeaWsOe7rei0ueW8gOmAmg==');
        }else{
            $domain_time = '' . date("Y-m-d", $domain_time) . '';
        }
		$domain = trim( preg_replace( "/http(s)?:\/\//", "", rtrim($_W['siteroot'],"/") )  );
		$ip = gethostbyname($_SERVER['HTTP_HOST']);
		$setting = setting_load('site');
		$id = ((isset($setting['site']['key']) ? $setting['site']['key'] : '0'));
		$auth = get_auth();
		load()->func('communication');
		if(!$id) $id = rand(56865,99999);
		if ($_W['ispost']) {
			 
			if (empty($_GPC['domain'])) {
				show_json(0,'域名不能为空!');
			}
			if (empty($_GPC['code'])) {
				show_json(0,'请填写授权码!');
			}
			if (empty($_GPC['id'])) {
				show_json(0,'您还没未注册站点!');
			}
			$data = array( 'ip' => $ip, 'siteid' => $id, 'code' => $_GPC['code'], 'domain' => $domain);
			$result = auth_grant($data);
			
			if ($result['errno'] == '1') {
                show_json(0,$result['message']);
			} else {
                $set = pdo_fetch('select id, sets from ' . tablename('ewei_shop_sysset') . ' order by id asc limit 1');
                $sets = iunserializer($set['sets']);
                if (!is_array($sets)) {
                    $sets = array();
                }
				if(!empty($result['auid'])){
					$id=$result['auid'];
				}
                $sets['auth'] = array(
                    'ip' => $ip,
                    'id' => $id,
                    'code' => $_GPC['code'],
                    'domain' =>$domain
                );
                if (empty($set)) {
                    pdo_insert('ewei_shop_sysset', array('sets' => iserializer($sets), 'uniacid' => $_W['uniacid']));
                } else {
					
                    pdo_update('ewei_shop_sysset', array('sets' => iserializer($sets)), array('id' => $set['id']));
                }
                $result['status'] = 1;
                $result['result']['url'] = webUrl('system/auth');
                die(json_encode($result));
            }
		}
		$result = array('status'=>0);
		if (!empty($ip) && !empty($id) && !empty($auth['code'])) {
			load()->func('communication');
			$data = array('ip' => $ip, 'id' => $id, 'code' => $auth['code'], 'domain' => $domain);
            $result = auth_checkauth($data);
		}
		include $this->template();
	}

}
