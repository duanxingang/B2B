<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Company_EweiShopV2Page extends MobilePage
{
    //注册
    public function register()
    {
        global $_W;
        global $_GPC;
        $app_uid = isset($_GPC['app_uid']) ? $_GPC['app_uid'] : 0;

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

    //修改
    public function edit()
    {
        global $_W;
        global $_GPC;
        $_W["openid"] = $_SESSION['wechat_openid'];
        $member = pdo_fetch('select * from ' . tablename('ewei_shop_member') . ' where openid=:openid and uniacid=:uniacid limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $_W["openid"]));
        $member['yao_card_time'] = date('Y-m-d',$member['yao_card_time']);
        $member['gsp_card_time'] = date('Y-m-d',$member['gsp_card_time']);
        $imgs_new = unserialize($member['orther_img']);
        $member['food_img'] = $imgs_new['food_img'];
        $member['arrive_img'] = $imgs_new['arrive_img'];
        $member['appliance_img'] = $imgs_new['appliance_img'];

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

    //添加保存
    public function add()
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
            if($_GPC['report_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传年度报告图片']) );
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
            if($_GPC['app_uid'] != 0){
                //手机端其他浏览器
                pdo_update('ewei_shop_member', $data, array('id' => $_GPC['app_uid']));
                $member = pdo_fetch('select id,openid,mobile,pwd,salt from ' . tablename('ewei_shop_member') . ' where id=:id and mobileverify=1 and uniacid=:uniacid limit 1', array(':id' => $_GPC['app_uid'], ':uniacid' => $_W['uniacid']));
                m('account')->setLogin($member);
                exit(json_encode(['code'=>1,'message'=>'注册成功','url'=>'./app/index.php?' . $_SERVER['QUERY_STRING']]));
            }else{
                //微信浏览器
                $_W["openid"] = $_SESSION['wechat_openid'];
                if($_W['openid']){
                    pdo_update('ewei_shop_member', $data, array('openid'=>$_W['openid'], 'uniacid' => $_W['uniacid']));
                    exit(json_encode(['code'=>1,'message'=>'注册成功','url'=>'./app/index.php?' . $_SERVER['QUERY_STRING']]));
                }else{
                    exit( json_encode(['code'=>0,'message'=>'网络错误，请稍后再试~~']));
                }
            }

        }catch (Exception $e){
            exit( json_encode(['code'=>0,'message'=>'网络错误，请稍后再试~~']) );
        }
    }

    //修改保存
    public function post()
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
            if($_GPC['report_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传年度报告图片']) );
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
            $_W["openid"] = $_SESSION['wechat_openid'];
            if($_W['openid']){
                pdo_update('ewei_shop_member', $data, array('openid'=>$_W['openid'], 'uniacid' => $_W['uniacid']));
                exit(json_encode(['code'=>1,'message'=>'修改成功','url'=>'./app/index.php?' . $_SERVER['QUERY_STRING']]));
            }else{
                exit( json_encode(['code'=>0,'message'=>'网络错误，请稍后再试~~']));
            }

        }catch (Exception $e){
            exit( json_encode(['code'=>0,'message'=>'网络错误，请稍后再试~~']) );
        }
    }

    //上传图片
    public function upload()
    {
        try{

            if( !empty($_FILES['imgFile']["name"]) )
            {
                if( strrchr($_FILES['imgFile']["name"], ".") === false )
                {
                    $_FILES['imgFile']["name"] = $_FILES['imgFile']["name"] . ".jpg";
                }
                $result = $this->upload_file($_FILES['imgFile']);
                exit( json_encode($result) );
            }
            else
            {
                $result["message"] = "请选择要上传的图片！";
                $result["status"] = "error";
                exit( json_encode($result) );
            }
        }catch (Exception $e){
            $result["message"] = "上传失败，请重试！";
            $result["status"] = "error";
            exit( json_encode($result) );
        }
    }

    public function upload_file($uploadfile)
    {
        global $_W;
        global $_GPC;
        $result["status"] = "error";
        if( $uploadfile["error"] != 0 )
        {
            $result["message"] = "上传失败，请重试！";
            return $result;
        }
        load()->func("file");
        $path = "/images/ewei_shop/" . $_W["uniacid"];
        if( !is_dir(ATTACHMENT_ROOT . $path) )
        {
            mkdirs(ATTACHMENT_ROOT . $path);
        }
        $_W["uploadsetting"] = array( );
        $_W["uploadsetting"]["image"]["folder"] = $path;
        $_W["uploadsetting"]["image"]["extentions"] = $_W["config"]["upload"]["image"]["extentions"];
        $_W["uploadsetting"]["image"]["limit"] = $_W["config"]["upload"]["image"]["limit"];
        $file = file_upload($uploadfile, "image");
        if( is_error($file) )
        {
            $result["message"] = $file["message"];
            return $result;
        }
        if( !empty($_W["setting"]["remote"][$_W["uniacid"]]["type"]) )
        {
            $_W["setting"]["remote"] = $_W["setting"]["remote"][$_W["uniacid"]];
        }
        if( function_exists("file_remote_upload") )
        {
            $remote = file_remote_upload($file["path"]);
            if( is_error($remote) )
            {
                $result["message"] = $remote["message"];
                return $result;
            }
        }
        $result["status"] = "success";
        $result["url"] = $file["url"];
        $result["error"] = 0;
        $result["filename"] = $file["path"];
        $result["url"] = tomedia(trim($result["filename"]));
        return $result;
    }



}