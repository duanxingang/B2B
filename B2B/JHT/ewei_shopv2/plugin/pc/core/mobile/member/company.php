<?php
if (!(defined("IN_IA")))
{
    exit("Access Denied");
}
require EWEI_SHOPV2_PLUGIN . "pc/core/page_login_mobile.php";
class Company_EweiShopV2Page extends PcMobileLoginPage
{
    protected $member;
    public function __construct()
    {
        global $_W;
        global $_GPC;
        parent::__construct();
        $m = m('member')->getInfo($_W['openid']);
        if($m['level'] == 0){
            $m['level_name'] = '普通会员';
        }else{
            $m['level_name'] = pdo_getcolumn('ewei_shop_member_level', array('id' => $m['level']), 'levelname');
        }
        $this->member = $m;
    }

    public function main()
    {
        global $_W;
        global $_GPC;
        $returnurl = urldecode(trim($_GPC['returnurl']));
        $member = $this->member;
        $imgs_new = unserialize($member['orther_img']);
        $member['food_img'] = $imgs_new['food_img'];
        $member['arrive_img'] = $imgs_new['arrive_img'];
        $member['appliance_img'] = $imgs_new['appliance_img'];
        $wapset = m('common')->getSysset('wap');
        $ice_menu_array = array( array('menu_key' => 'index', 'menu_name' => '企业信息', 'menu_url' => mobileUrl('pc.member.company')) );
        $nav_link_list = array( array('link' => mobileUrl('pc'), 'title' => '首页'), array('link' => mobileUrl('pc.member'), 'title' => '我的商城'), array('title' => '企业信息') );
                $id = intval($_GPC["id"]);
        $area_set = m("util")->get_area_config_set();
        $new_area = intval($area_set["new_area"]);
        $address_street = intval($area_set["address_street"]);
        if( !empty($id) )
        {
            $address = pdo_fetch("select * from " . tablename("ewei_shop_member_address") . " where id=:id and openid=:openid and uniacid=:uniacid limit 1 ", array( ":id" => $id, ":uniacid" => $_W["uniacid"], ":openid" => $_W["openid"] ));
            if( empty($address["datavalue"]) )
            {
                $provinceName = $address["province"];
                $citysName = $address["city"];
                $countyName = $address["area"];
                $province_code = 0;
                $citys_code = 0;
                $county_code = 0;
                $path = EWEI_SHOPV2_PATH . "static/js/dist/area/AreaNew.xml";
                $xml = file_get_contents($path);
                $array = xml2array($xml);
                $newArr = array( );
                if( is_array($array["province"]) )
                {
                    foreach( $array["province"] as $i => $v )
                    {
                        if( 0 < $i && $v["@attributes"]["name"] == $provinceName && !is_null($provinceName) && $provinceName != "" )
                        {
                            $province_code = $v["@attributes"]["code"];
                            if( is_array($v["city"]) )
                            {
                                if( !isset($v["city"][0]) )
                                {
                                    $v["city"] = array( $v["city"] );
                                }
                                foreach( $v["city"] as $ii => $vv )
                                {
                                    if( $vv["@attributes"]["name"] == $citysName && !is_null($citysName) && $citysName != "" )
                                    {
                                        $citys_code = $vv["@attributes"]["code"];
                                        if( is_array($vv["county"]) )
                                        {
                                            if( !isset($vv["county"][0]) )
                                            {
                                                $vv["county"] = array( $vv["county"] );
                                            }
                                            foreach( $vv["county"] as $iii => $vvv )
                                            {
                                                if( $vvv["@attributes"]["name"] == $countyName && !is_null($countyName) && $countyName != "" )
                                                {
                                                    $county_code = $vvv["@attributes"]["code"];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if( $province_code != 0 && $citys_code != 0 && $county_code != 0 )
                {
                    $address["datavalue"] = $province_code . " " . $citys_code . " " . $county_code;
                    pdo_update("ewei_shop_member_address", $address, array( "id" => $id, "uniacid" => $_W["uniacid"], "openid" => $_W["openid"] ));
                }
            }
            $show_data = 1;
            if( !empty($new_area) && empty($address["datavalue"]) || empty($new_area) && !empty($address["datavalue"]) )
            {
                $show_data = 0;
            }
        }
        include $this->template();
    }

    //修改企业信息
    public function edit()
    {
        try{
            global $_W;
            global $_GPC;
            //判断
            if($_GPC['company'] == '')exit( json_encode(['code'=>0,'message'=>'企业名称不能为空']) );
            if($_GPC['contact_name'] == '')exit( json_encode(['code'=>0,'message'=>'姓名不能为空']) );
            if($_GPC['contact_phone'] == '')exit( json_encode(['code'=>0,'message'=>'手机号码不能为空']) );
            if(!preg_match("/^1[345678]{1}\d{9}$/",$_GPC['contact_phone']))exit( json_encode(['code'=>0,'message'=>'请输入有效的手机号码']) );
            if($_GPC['company_address'] == '')exit( json_encode(['code'=>0,'message'=>'企业地址不能为空']) );
            if($_GPC['company_areas'] == '')exit( json_encode(['code'=>0,'message'=>'详细地址不能为空']) );
            if($_GPC['invoice_type'] == '')exit( json_encode(['code'=>0,'message'=>'发票类型不能为空']) );
            if($_GPC['yao_card'] == '')exit( json_encode(['code'=>0,'message'=>'药品许可证不能为空']) );
            if($_GPC['yao_card_time'] == '')exit( json_encode(['code'=>0,'message'=>'药品许可证有效期不能为空']) );
            if($_GPC['gsp_card'] == '')exit( json_encode(['code'=>0,'message'=>'GSP证书不能为空']) );
            if($_GPC['gsp_card_time'] == '')exit( json_encode(['code'=>0,'message'=>'GSP证书有效期不能为空']) );
            if($_GPC['business_card'] == '')exit( json_encode(['code'=>0,'message'=>'营业执照号码不能为空']) );
            if($_GPC['business_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传营业执照']) );
            if($_GPC['yao_card_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传药品许可证图片']) );
            if($_GPC['quality_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传质量保证协议图片']) );
            if($_GPC['buyer_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传采购委托书图片']) );
            if($_GPC['buyerid_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传采购委托人身份证图片']) );
            if($_GPC['arrive_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传收货委托书图片']) );
            if($_GPC['arriveid_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传收货委托人身份证图片']) );
            if($_GPC['bill_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传发票资料图片']) );
            if($_GPC['gsp_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传GSP证书图片']) );
            $food_img = empty($_GPC['food_img']) ? ' ' : $_GPC['food_img'];
            $arrive_img = empty($_GPC['arrive_img']) ? ' ' : $_GPC['arrive_img'];
            $appliance_img = empty($_GPC['appliance_img']) ? ' ' : $_GPC['appliance_img']; 
            $orther_img = array('food_img'=>$food_img,'arrive_img'=>$arrive_img,'appliance_img'=>$appliance_img);
            $orther_img = serialize($orther_img);
            $data = [
                'company'=>$_GPC['company'],
                'contact_name'=>$_GPC['contact_name'],
                'contact_phone'=>$_GPC['contact_phone'],
                'company_address'=>$_GPC['company_address'],
                'company_areas'=>$_GPC['company_areas'],
                'invoice_type'=>$_GPC['invoice_type'],
                'yao_card'=>$_GPC['yao_card'],
                'yao_card_time'=>strtotime($_GPC['yao_card_time']),
                'gsp_card'=>$_GPC['gsp_card'],
                'gsp_card_time'=>strtotime($_GPC['gsp_card_time']),
                'business_card'=>$_GPC['business_card'],
                'business_img'=>$_GPC['business_img'],
                'yao_card_img'=>$_GPC['yao_card_img'],
                'gsp_img' => $_GPC['gsp_img'],
                'bill_img' => $_GPC['bill_img'],
                'report_img' => $_GPC['report_img'],
                'arriveid_img' => $_GPC['arriveid_img'],
                'buyerid_img' => $_GPC['buyerid_img'], 
                'buyer_img' => $_GPC['buyer_img'],
                'quality_img' => $_GPC['quality_img'],
                'orther_img' => $orther_img,
                'is_shen'=>0,
            ];


            pdo_update('ewei_shop_member', $data, array('id' => $_GPC['app_uid']));
            exit(json_encode(['code'=>1,'message'=>'修改成功']));


        }catch (Exception $e){
            exit( json_encode(['code'=>0,'message'=>'网络错误，请稍后再试~~']) );
        }
    }

}
?>