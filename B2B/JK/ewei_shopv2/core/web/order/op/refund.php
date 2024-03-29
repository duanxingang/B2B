<?php  if( !defined("IN_IA") ) 
{
	exit( "Access Denied" );
}
class Refund_EweiShopV2Page extends WebPage 
{
	protected function opData() 
	{
		global $_W;
		global $_GPC;
		$id = intval($_GPC["id"]);
		$refundid = intval($_GPC["refundid"]);
		$goodsid = intval($_GPC["goodsid"]);
		$item = pdo_fetch("SELECT * FROM " . tablename("ewei_shop_order") . " WHERE id = :id and uniacid=:uniacid Limit 1", array( ":id" => $id, ":uniacid" => $_W["uniacid"] ));
		if( empty($item) ) 
		{
			if( $_W["isajax"] ) 
			{
				show_json(0, "未找到订单!");
			}
			$this->message("未找到订单!", "", "error");
		}
		if($item['ispartrefund'] == 0){
			$refundOne = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_refund") . " WHERE uniacid=:uniacid and orderid=:orderid and status>=0", array(":uniacid" => $_W["uniacid"], ":orderid"=>$id));
            foreach($refundOne as $kk=>$vv){
                $refundOne[$kk]['goods_title'] = pdo_getcolumn('ewei_shop_goods', array('id' => $vv['goodsid']), 'title');
                $refundOne[$kk]["imgs"] = iunserializer($vv["imgs"]);
            }
		}
		if(empty($refundid)){
			$refundid = $item["refundid"];
		}
		if( !empty($refundid) ) 
		{
			$refund = pdo_fetch("select * from " . tablename("ewei_shop_order_refund") . " where id=:id limit 1", array( ":id" => $refundid ));
			$refund["imgs"] = iunserializer($refund["imgs"]);
		}
		$r_type = array( "退款", "退货退款", "换货" );
		return array( "id" => $id, "item" => $item, "refund" => $refund, "r_type" => $r_type, "refundOne" => $refundOne, 'goodsid' => $goodsid, "refundid" => $refundid);
	}

	public function refundOneGoods($orderid) 
    {
        global $_W;
        global $_GPC;
        $refundOne = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_refund") . " WHERE uniacid=:uniacid and orderid=:orderid and status>0", array(":uniacid" => $_W["uniacid"], ":orderid"=> $orderid));
        $ordergoods = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_goods") . " WHERE uniacid=:uniacid and orderid=:orderid", array(":uniacid" => $_W["uniacid"], ":orderid"=> $orderid));
        $time = time();
        $goodsnum = count($ordergoods);
        if(!empty($refundOne)){
            $num = count($refundOne);
            if($num < $goodsnum){
                foreach($refundOne as $val){
                    if($val['price'] == $val['applyprice']){
                        $order_data = array( );
                        $order_data["refundtime"] = $time;
                        $order_data["refundstate"] = -1;
                        pdo_update("ewei_shop_order", $order_data, array( "id" => $orderid, "uniacid" => $_W['uniacid']));
                    }
                }   
            }else{
                for($i=0;$i<$num;$i++){
                    $price[] = $refundOne[$i]['price'];
                    $orderprice = $refundOne[$i]['orderprice'];
                }
                $refundprice = array_sum($price);
                $orderprice = round($orderprice,2);
                $refundprice = round($refundprice,2);
                if($orderprice == $refundprice){
                    $order_data = array( );
                    $order_data["refundtime"] = $time;
                    $order_data["status"] = -1;
                    $order_data["refundstate"] = -1;
                    pdo_update("ewei_shop_order", $order_data, array( "id" => $orderid, "uniacid" => $_W['uniacid']));
                }
            }
        }
     }

	public function submit()
	{
		global $_W;
		global $_GPC;
		global $_S;
		$opdata = $this->opData();
		extract($opdata);

		if( $_W["ispost"] )
		{
		    if(empty($_GPC["refundstatus"])){
                show_json(0, "请选择处理结果");
            }
			$goodsid = $refund['goodsid'];
			$shopset = $_S["shop"];
			$dataOne = pdo_fetch("SELECT orderid FROM " . tablename("ewei_shop_order_refund") . " WHERE id = :id and goodsid=:goodsid Limit 1", array( ":id" => $refundid, ":goodsid" => $goodsid ));
		    $orderid = $dataOne['orderid'];
			if( empty($item["refundstate"]) )
			{
				show_json(0, "订单未申请维权，不需处理！");
			}
			if( $refund["status"] < 0 || $refund["status"] == 1 ) 
			{
				pdo_update("ewei_shop_order", array( "refundstate" => 0 ), array( "id" => $item["id"], "uniacid" => $_W["uniacid"] ));
				show_json(0, "未找需要处理的维权申请，不需处理！");
			}
			if( empty($refund["refundno"]) ) 
			{
				$refund["refundno"] = m("common")->createNO("order_refund", "refundno", "SR");
				pdo_update("ewei_shop_order_refund", array( "refundno" => $refund["refundno"] ), array( "id" => $refund["id"] ));
			}



			if($item['ispartrefund'] == 0){
	            $refundstatus = intval($_GPC["refundstatus"]);
				$refundcontent = trim($_GPC["refundcontent"]);
				$time = time();
				$change_refund = array( );
				$uniacid = $_W["uniacid"];
                $refundnum = intval($_GPC["refundnum"]);
				if( $refundstatus == 0 ) {
					show_json(1);
				} else {
				    //修改退货数量
                    if(!empty($refundnum)){
                        $olds = pdo_fetch("SELECT refundnum, applyprice FROM ".tablename('ewei_shop_order_refund')." WHERE id = :id LIMIT 1", array(':id' => $refundid));
                        if($olds['refundnum'] != $refundnum){
                            $new_applyprice =  (number_format(($olds['applyprice']/$olds['refundnum']),2))*$refundnum;
                            pdo_update("ewei_shop_order_refund", array( "refundnum" => $refundnum ,"applyprice"=>$new_applyprice), array( "id" => $refundid ));
                            $refund["applyprice"] = $new_applyprice;
                            plog("order.op.refund.submit", "订单申请退款refundid : " . $refundid . " 申请数量 : " . $olds['refundnum'] . " 退货数量 : " .$refundnum);
                        }

                    }


					if( $refundstatus == 3 ) {
					    $raid = $_GPC["raid"];
					    $message = trim($_GPC["message"]);
					    if( $raid == 0 ) {
						    $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where isdefault=1 and uniacid=:uniacid and merchid=0 limit 1", array( ":uniacid" => $uniacid ));
					    } else {
						    $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where id=:id and uniacid=:uniacid and merchid=0 limit 1", array( ":id" => $raid, ":uniacid" => $uniacid ));
					    }
                        if( empty($raddress) )
                        {
                            $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where uniacid=:uniacid and merchid=0 order by id desc limit 1", array( ":uniacid" => $uniacid ));
                        }
                        unset($raddress["uniacid"]);
                        unset($raddress["openid"]);
                        unset($raddress["isdefault"]);
                        unset($raddress["deleted"]);
                        $raddress = iserializer($raddress);
                        $change_refund["reply"] = "";
                        $change_refund["refundaddress"] = $raddress;
                        $change_refund["refundaddressid"] = $raid;
                        $change_refund["message"] = $message;
                        if( empty($refund["operatetime"]) )
                        {
                            $change_refund["operatetime"] = $time;
                        }
                        if( $refund["status"] != 4 )
                        {
                            $change_refund["status"] = 3;
                        }
					    pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
					    m("notice")->sendOrderMessage($item["id"], true, $raid);
				    } else {
                        if( $refundstatus == 5 )
                        {
                            $change_refund["rexpress"] = $_GPC["rexpress"];
                            $change_refund["rexpresscom"] = $_GPC["rexpresscom"];
                            $change_refund["rexpresssn"] = trim($_GPC["rexpresssn"]);
                            $change_refund["status"] = 5;
                            if( $refund["status"] != 5 && empty($refund["returntime"]) )
                            {
                                $change_refund["returntime"] = $time;
                                if( empty($refund["operatetime"]) )
                                {
                                    $change_refund["operatetime"] = $time;
                                }
                            }
                            pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
                            m("notice")->sendOrderMessage($item["id"], true);
                        }
                        else
                        {
                            if( $refundstatus == 10 )
                            {
                                $refund_data["status"] = 1;
                                $refund_data["refundtime"] = $time;
                                pdo_update("ewei_shop_order_refund", $refund_data, array( "id" => $refundid, "uniacid" => $uniacid));
                                $goods_data = array( );
                                $goods_data["status1"] = 3;
                                pdo_update("ewei_shop_order_goods", $goods_data, array("orderid" => $item['id'], "goodsid" => $goodsid, "uniacid" => $uniacid ));
                                m("notice")->sendOrderMessage($item["id"], true);
                                if($irem['goodsprice'] == sum($refundOne['applyprice'])){
                                    pdo_update("ewei_shop_order", array('status' => -1), array( "id" => $item['id'], "uniacid" => $uniacid));
                                }

                            }
                            else
                            {
                                if( $refundstatus == 1 )
                                {
                                    if( 0 < $item["parentid"] )
                                    {
                                        $parent_item = pdo_fetch("SELECT id,ordersn,ordersn2,price,transid,paytype,apppay FROM " . tablename("ewei_shop_order") . " WHERE id = :id and uniacid=:uniacid Limit 1", array( ":id" => $item["parentid"], ":uniacid" => $_W["uniacid"] ));
                                        if( empty($parent_item) )
                                        {
                                            show_json(0, "未找到退款订单!");
                                        }
                                        $order_price = $parent_item["price"];
                                        $ordersn = $parent_item["ordersn"];
                                        $item["transid"] = $parent_item["transid"];
                                        $item["paytype"] = $parent_item["paytype"];
                                        $item["apppay"] = $parent_item["apppay"];
                                        if( !empty($parent_item["ordersn2"]) )
                                        {
                                            $var = sprintf("%02d", $parent_item["ordersn2"]);
                                            $ordersn .= "GJ" . $var;
                                        }
                                    }
                                    else
                                    {
                                        $borrowopenid = $item["borrowopenid"];
                                        $ordersn = $item["ordersn"];
                                        if($item["wechatpay"] > 0){
                                            $ordersn = $item["ordersn"].'-'.$item["wechatpay"];
                                        }
                                        $order_price = $item["price"];
                                        if( !strexists($borrowopenid, "2088") && !is_numeric($borrowopenid) && !empty($item["ordersn2"]) )
                                        {
                                            $var = sprintf("%02d", $item["ordersn2"]);
                                            $ordersn .= "GJ" . $var;
                                        }
                                    }
                                    $applyprice = $refund["applyprice"];
                                    $pay_refund_price = 0;
                                    $dededuct__refund_price = 0;
                                    if( $applyprice <= $item["price"] )
                                    {
                                        $pay_refund_price = $applyprice;
                                        $dededuct__refund_price = 0;
                                    }
                                    else
                                    {
                                        if( $item["price"] < $applyprice && $applyprice <= $item["price"] + $item["deductcredit2"] )
                                        {
                                            $pay_refund_price = $item["price"];
                                            $dededuct__refund_price = $applyprice - $pay_refund_price;
                                        }
                                        else
                                        {
                                            show_json(0, "退款申请的金额错误.请联系买家重新申请!");
                                        }
                                    }
                                    $goods = pdo_fetchall("SELECT g.id,g.credit, o.total,o.realprice,g.isfullback FROM " . tablename("ewei_shop_order_goods") . " o left join " . tablename("ewei_shop_goods") . " g on o.goodsid=g.id " . " WHERE o.orderid=:orderid and o.uniacid=:uniacid", array( ":orderid" => $item["id"], ":uniacid" => $uniacid ));
                                    $refundtype = 0;
                                    if( empty($item["transid"]) && $item["paytype"] == 22 && empty($item["apppay"]) )
                                    {
                                        $item["paytype"] = 23;
                                    }
                                    if( !empty($item["transid"]) && $item["paytype"] == 22 && empty($item["apppay"]) && strexists($item["borrowopenid"], "2088") )
                                    {
                                        $item["paytype"] = 23;
                                    }
                                    $ispeerpay = m("order")->checkpeerpay($item["id"]);
                                    if( !empty($ispeerpay) )
                                    {
                                        $item["paytype"] = 21;
                                    }
                                    if( $item["paytype"] == 1 )
                                    {
                                        m("member")->setCredit($item["openid"], "credit2", $pay_refund_price, array( 0, $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] ));
                                        $result = true;
                                        $refundtype = 0;
                                    }
                                    else
                                    {
                                        if( $item["paytype"] == 21 )
                                        {
                                            if( $item["apppay"] == 2 )
                                            {
                                                $result = m("finance")->wxapp_refund($item["openid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["apppay"]) ? true : false));
                                            }
                                            else
                                            {
                                                if( !empty($ispeerpay) )
                                                {
                                                    $pid = $ispeerpay["id"];
                                                    $peerpaysql = "SELECT * FROM " . tablename("ewei_shop_order_peerpay_payinfo") . " WHERE pid = :pid";
                                                    $peerpaylist = pdo_fetchall($peerpaysql, array( ":pid" => $pid ));
                                                    if( empty($peerpaylist) )
                                                    {
                                                        show_json(0, "没有人帮他代付过,无需退款");
                                                    }
						                            else     
                                                    {//代付单品退款修改  2019.08.26
                                                            $result = m("finance")->refund($item["openid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["apppay"]) ? true : false));
                                                    }
                                                    // foreach( $peerpaylist as $k => $v )
                                                    // {
                                                    //     if( empty($v["tid"]) )
                                                    //     {
                                                    //         m("member")->setCredit($v["openid"], "credit2", $v["price"], array( 0, $shopset["name"] . "退款: " . $v["price"] . "元 代付订单号: " . $item["ordersn"] ));
                                                    //         $result = true;
                                                    //         continue;
                                                    //     }
                                                    //     $result = m("finance")->refund($v["openid"], $v["tid"], $refund["refundno"] . $v["id"], $v["price"] * 100, $v["price"] * 100, (!empty($item["apppay"]) ? true : false));
                                                    // }
                                                }
                                                else
                                                {
                                                    if( 0 < $pay_refund_price )
                                                    {
                                                        if( empty($item["isborrow"]) )
                                                        {
                                                            $result = m("finance")->refund($item["openid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["apppay"]) ? true : false));
                                                        }
                                                        else
                                                        {
                                                            $result = m("finance")->refundBorrow($item["borrowopenid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["ordersn2"]) ? 1 : 0));
                                                        }
                                                    }
                                                }
                                            }
                                            $refundtype = 2;
                                        }
                                        else
                                        {
                                            if( $item["paytype"] == 22 )
                                            {
                                                $sec = m("common")->getSec();
                                                $sec = iunserializer($sec["sec"]);
                                                if( !empty($item["apppay"]) )
                                                {
                                                    if( !empty($sec["app_alipay"]["private_key_rsa2"]) )
                                                    {
                                                        $sign_type = "RSA2";
                                                        $privatekey = $sec["app_alipay"]["private_key_rsa2"];
                                                    }
                                                    else
                                                    {
                                                        $sign_type = "RSA";
                                                        $privatekey = $sec["app_alipay"]["private_key"];
                                                    }
                                                    if( empty($privatekey) || empty($sec["app_alipay"]["appid"]) )
                                                    {
                                                        show_json(0, "支付参数错误，私钥为空或者APPID为空!");
                                                    }
                                                    $params = array( "out_request_no" => time(), "out_trade_no" => $ordersn, "refund_amount" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] );
                                                    $config = array( "app_id" => $sec["app_alipay"]["appid"], "privatekey" => $privatekey, "publickey" => "", "alipublickey" => "", "sign_type" => $sign_type );
                                                    $result = m("finance")->newAlipayRefund($params, $config);
                                                }
                                                else
                                                {
                                                    if( !empty($sec["alipay_pay"]) )
                                                    {
                                                        if( empty($sec["alipay_pay"]["private_key"]) || empty($sec["alipay_pay"]["appid"]) )
                                                        {
                                                            show_json(0, "支付参数错误，私钥为空或者APPID为空!");
                                                        }
                                                        if( $sec["alipay_pay"]["alipay_sign_type"] == 1 )
                                                        {
                                                            $sign_type = "RSA2";
                                                        }
                                                        else
                                                        {
                                                            $sign_type = "RSA";
                                                        }
                                                        $params = array( "out_request_no" => time(), "out_trade_no" => $item["ordersn"], "refund_amount" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] );
                                                        $config = array( "app_id" => $sec["alipay_pay"]["appid"], "privatekey" => $sec["alipay_pay"]["private_key"], "publickey" => "", "alipublickey" => "", "sign_type" => $sign_type );
                                                        $result = m("finance")->newAlipayRefund($params, $config);
                                                    }
                                                    else
                                                    {
                                                        if( empty($item["transid"]) )
                                                        {
                                                            show_json(0, "仅支持 升级后此功能后退款的订单!");
                                                        }
                                                        $setting = uni_setting($_W["uniacid"], array( "payment" ));
                                                        if( !is_array($setting["payment"]) )
                                                        {
                                                            return error(1, "没有设定支付参数");
                                                        }
                                                        $alipay_config = $setting["payment"]["alipay"];
                                                        $batch_no_money = $pay_refund_price * 100;
                                                        $batch_no = date("Ymd") . "RF" . $item["id"] . "MONEY" . $batch_no_money;
                                                        $res = m("finance")->AlipayRefund(array( "trade_no" => $item["transid"], "refund_price" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] ), $batch_no, $alipay_config);
                                                        if( is_error($res) )
                                                        {
                                                            show_json(0, $res["message"]);
                                                        }
                                                        show_json(1, array( "url" => $res ));
                                                    }
                                                }
                                                $refundtype = 3;
                                            }
                                            else
                                            {
                                                if( $item["paytype"] == 23 && !empty($item["isborrow"]) )
                                                {
                                                    $result = m("finance")->refundBorrow($item["borrowopenid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["ordersn2"]) ? 1 : 0));
                                                    $refundtype = 4;
                                                }
                                                else
                                                {
                                                    if( $pay_refund_price < 1 )
                                                    {
                                                        show_json(0, "退款金额必须大于1元，才能使用微信企业付款退款!");
                                                    }
                                                    if( 0 < $pay_refund_price )
                                                    {
                                                        $result = m("finance")->pay($item["openid"], 1, $pay_refund_price * 100, $refund["refundno"], $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"]);
                                                    }
                                                    $refundtype = 1;
                                                }
                                            }
                                        }
                                    }
                                    if( is_error($result) )
                                    {
                                        show_json(0, $result["message"]);
                                    }
                                    if( 0 < $goods["isfullback"] )
                                    {
                                        m("order")->fullbackstop($item["id"]);
                                    }
                                    if( 0 < $dededuct__refund_price )
                                    {
                                        $item["deductcredit2"] = $dededuct__refund_price;
                                        m("order")->setDeductCredit2($item);
                                    }
                                    $change_refund["reply"] = "";
                                    $change_refund["status"] = 1;
                                    $change_refund["refundtype"] = $refundtype;
                                    $change_refund["price"] = $applyprice;
                                    $change_refund["refundtime"] = $time;
                                    if( empty($refund["operatetime"]) )
                                    {
                                        $change_refund["operatetime"] = $time;
                                    }
                                    pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
                                    $goods_data = array( );
                                    $goods_data["status1"] = 3;
                                    pdo_update("ewei_shop_order_goods", $goods_data, array("orderid" => $orderid, "goodsid" => $goodsid, "uniacid" => $uniacid ));
                                    m("order")->setGiveBalance($item["id"], 2);
                                    m("order")->setStocksAndCredits($item["id"], 2);
                                    if( $refund["orderprice"] == $refund["applyprice"] && com("coupon") && !empty($item["couponid"]) )
                                    {
                                        com("coupon")->returnConsumeCoupon($item["id"]);
                                    }
                                    foreach( $goods as $g )
                                    {
                                        $salesreal = pdo_fetchcolumn("select ifnull(sum(total),0) from " . tablename("ewei_shop_order_goods") . " og " . " left join " . tablename("ewei_shop_order") . " o on o.id = og.orderid " . " where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1", array( ":goodsid" => $g["id"], ":uniacid" => $uniacid ));
                                        pdo_update("ewei_shop_goods", array( "salesreal" => $salesreal ), array( "id" => $g["id"] ));
                                    }
                                    $log = "订单退款 ID: " . $item["id"] . " 订单号: " . $item["ordersn"];
                                    if( 0 < $item["parentid"] )
                                    {
                                        $log .= " 父订单号:" . $ordersn;
                                    }
                                    plog("order.op.refund.submit", $log);
                                    m("notice")->sendOrderMessage($item["id"], true);
                                }
                                else
                                {
                                    if( $refundstatus == -1 )
                                    {
                                        pdo_update("ewei_shop_order_refund", array( "reply" => $refundcontent, "status" => -1, "endtime" => $time ), array( "id" => $refundid ));
                                        $goods_data = array( );
                                        $goods_data["status1"] = -1;
                                        pdo_update("ewei_shop_order_goods", $goods_data, array("orderid" => $item["id"], "goodsid" => $goodsid, "orderid" => $orderid));
                                        plog("order.op.refund.submit", "订单退款拒绝 ID: " . $item["id"] . " 订单号: " . $item["ordersn"] . " 原因: " . $refundcontent);
                                        m("notice")->sendOrderMessage($item["id"], true);
                                    }
                                    else
                                    {
                                        if( $refundstatus == 2 )
                                        {
                                            $refundtype = 2;
                                            $change_refund["reply"] = "";
                                            $change_refund["status"] = 1;
                                            $change_refund["refundtype"] = $refundtype;
                                            $change_refund["price"] = $refund["applyprice"];
                                            $change_refund["refundtime"] = $time;
                                            if( empty($refund["operatetime"]) )
                                            {
                                                $change_refund["operatetime"] = $time;
                                            }

                                            pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
                                            $goods_data = array( );
                                            $goods_data["status1"] = 3;
                                            pdo_update("ewei_shop_order_goods", $goods_data, array("orderid" => $item["id"], "goodsid" => $goodsid, "uniacid" => $uniacid ));
                                            m("order")->setGiveBalance($item["id"], 2);
                                            m("order")->setStocksAndCredits($item["id"], 2);
                                            if( $refund["orderprice"] == $refund["applyprice"] && com("coupon") && !empty($item["couponid"]) )
                                            {
                                                com("coupon")->returnConsumeCoupon($item["id"]);
                                            }
                                            $goods = pdo_fetchall("SELECT g.id,g.credit, o.total,o.realprice FROM " . tablename("ewei_shop_order_goods") . " o left join " . tablename("ewei_shop_goods") . " g on o.goodsid=g.id " . " WHERE o.orderid=:orderid and o.uniacid=:uniacid", array( ":orderid" => $item["id"], ":uniacid" => $uniacid ));
                                            $credits = m("order")->getGoodsCredit($goods);
                                            plog("order.op.refund.submit", "订单退款 ID: " . $item["id"] . " 订单号: " . $item["ordersn"] . " 手动退款!");
                                            if( $item["status"] == 3 && 0 < $credits )
                                            {
                                                m("member")->setCredit($item["openid"], "credit1", 0 - $credits, array( 0, $shopset["name"] . "退款扣除购物赠送积分: " . $credits . " 订单号: " . $item["ordersn"] ));
                                            }
                                            foreach( $goods as $g )
                                            {
                                                $salesreal = pdo_fetchcolumn("select ifnull(sum(total),0) from " . tablename("ewei_shop_order_goods") . " og " . " left join " . tablename("ewei_shop_order") . " o on o.id = og.orderid " . " where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1", array( ":goodsid" => $g["id"], ":uniacid" => $uniacid ));
                                                pdo_update("ewei_shop_goods", array( "salesreal" => $salesreal ), array( "id" => $g["id"] ));
                                            }
                                            m("notice")->sendOrderMessage($item["id"], true);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $this->refundOneGoods($id);
                show_json(1);
			}
            if($item['ispartrefund'] == 1){
            	$refundstatus = intval($_GPC["refundstatus"]);
			    $refundcontent = trim($_GPC["refundcontent"]);
			    $time = time();
			    $change_refund = array( );
			    $uniacid = $_W["uniacid"];
			    if( $refundstatus == 0 )
			    {
				    //show_json(1);
			    }
			    else
			    {
				    if( $refundstatus == 3 )
				    {
                        $raid = $_GPC["raid"];
                        $message = trim($_GPC["message"]);
                        if( $raid == 0 )
                        {
                            $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where isdefault=1 and uniacid=:uniacid and merchid=0 limit 1", array( ":uniacid" => $uniacid ));
                        }
                        else
                        {
                            $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where id=:id and uniacid=:uniacid and merchid=0 limit 1", array( ":id" => $raid, ":uniacid" => $uniacid ));
                        }
                        if( empty($raddress) )
                        {
                            $raddress = pdo_fetch("select * from " . tablename("ewei_shop_refund_address") . " where uniacid=:uniacid and merchid=0 order by id desc limit 1", array( ":uniacid" => $uniacid ));
                        }
                        unset($raddress["uniacid"]);
                        unset($raddress["openid"]);
                        unset($raddress["isdefault"]);
                        unset($raddress["deleted"]);
                        $raddress = iserializer($raddress);
                        $change_refund["reply"] = "";
                        $change_refund["refundaddress"] = $raddress;
                        $change_refund["refundaddressid"] = $raid;
                        $change_refund["message"] = $message;
                        if( empty($refund["operatetime"]) )
                        {
                            $change_refund["operatetime"] = $time;
                        }
                        if( $refund["status"] != 4 )
                        {
                            $change_refund["status"] = 3;
                        }
                        pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $item["refundid"] ));
                        m("notice")->sendOrderMessage($item["id"], true, $raid);
				    }
				    else
				    {
                        if( $refundstatus == 5 )
                        {
                            $change_refund["rexpress"] = $_GPC["rexpress"];
                            $change_refund["rexpresscom"] = $_GPC["rexpresscom"];
                            $change_refund["rexpresssn"] = trim($_GPC["rexpresssn"]);
                            $change_refund["status"] = 5;
                            if( $refund["status"] != 5 && empty($refund["returntime"]) )
                            {
                                $change_refund["returntime"] = $time;
                                if( empty($refund["operatetime"]) )
                                {
                                    $change_refund["operatetime"] = $time;
                                }
                            }
                            pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $item["refundid"] ));
                            m("notice")->sendOrderMessage($item["id"], true);
                        }
                        else
                        {
                            if( $refundstatus == 10 )
                            {
                                $refund_data["status"] = 1;
                                $refund_data["refundtime"] = $time;
                                pdo_update("ewei_shop_order_refund", $refund_data, array( "id" => $item["refundid"], "uniacid" => $uniacid ));
                                $order_data = array( );
                                $order_data["refundstate"] = 0;
                                $order_data["status"] = 3;
                                $order_data["refundtime"] = $time;
                                pdo_update("ewei_shop_order", $order_data, array( "id" => $item["id"], "uniacid" => $uniacid ));
                                m("notice")->sendOrderMessage($item["id"], true);
                            }
                            else
                            {
                                if( $refundstatus == 1 )
                                {
                                    if( 0 < $item["parentid"] )
                                    {
                                        $parent_item = pdo_fetch("SELECT id,ordersn,ordersn2,price,transid,paytype,apppay FROM " . tablename("ewei_shop_order") . " WHERE id = :id and uniacid=:uniacid Limit 1", array( ":id" => $item["parentid"], ":uniacid" => $_W["uniacid"] ));
                                        if( empty($parent_item) )
                                        {
                                            show_json(0, "未找到退款订单!");
                                        }
                                        $order_price = $parent_item["price"];
                                        $ordersn = $parent_item["ordersn"];
                                        $item["transid"] = $parent_item["transid"];
                                        $item["paytype"] = $parent_item["paytype"];
                                        $item["apppay"] = $parent_item["apppay"];
                                        if( !empty($parent_item["ordersn2"]) )
                                        {
                                            $var = sprintf("%02d", $parent_item["ordersn2"]);
                                            $ordersn .= "GJ" . $var;
                                        }
                                    }
                                    else
                                    {
                                        $borrowopenid = $item["borrowopenid"];
                                        $ordersn = $item["ordersn"];
                                        if($item["wechatpay"] > 0){
                                            $ordersn = $item["ordersn"].'-'.$item["wechatpay"];
                                        }
                                        $order_price = $item["price"];
                                        if( !strexists($borrowopenid, "2088") && !is_numeric($borrowopenid) && !empty($item["ordersn2"]) )
                                        {
                                            $var = sprintf("%02d", $item["ordersn2"]);
                                            $ordersn .= "GJ" . $var;
                                        }
                                    }
                                    $applyprice = $refund["applyprice"];
                                    $pay_refund_price = 0;
                                    $dededuct__refund_price = 0;
                                    if( $applyprice <= $item["price"] )
                                    {
                                        $pay_refund_price = $applyprice;
                                        $dededuct__refund_price = 0;
                                    }
                                    else
                                    {
                                        if( $item["price"] < $applyprice && $applyprice <= $item["price"] + $item["deductcredit2"] )
                                        {
                                            $pay_refund_price = $item["price"];
                                            $dededuct__refund_price = $applyprice - $pay_refund_price;
                                        }
                                        else
                                        {
                                            show_json(0, "退款申请的金额错误.请联系买家重新申请!");
                                        }
                                    }
                                    $goods = pdo_fetchall("SELECT g.id,g.credit, o.total,o.realprice,g.isfullback FROM " . tablename("ewei_shop_order_goods") . " o left join " . tablename("ewei_shop_goods") . " g on o.goodsid=g.id " . " WHERE o.orderid=:orderid and o.uniacid=:uniacid", array( ":orderid" => $item["id"], ":uniacid" => $uniacid ));
                                    $refundtype = 0;
                                    if( empty($item["transid"]) && $item["paytype"] == 22 && empty($item["apppay"]) )
                                    {
                                        $item["paytype"] = 23;
                                    }
                                    if( !empty($item["transid"]) && $item["paytype"] == 22 && empty($item["apppay"]) && strexists($item["borrowopenid"], "2088") )
                                    {
                                        $item["paytype"] = 23;
                                    }
                                    $ispeerpay = m("order")->checkpeerpay($item["id"]);
                                    if( !empty($ispeerpay) )
                                    {
                                        $item["paytype"] = 21;
                                    }
                                    if( $item["paytype"] == 1 )
                                    {
                                        m("member")->setCredit($item["openid"], "credit2", $pay_refund_price, array( 0, $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] ));
                                        $result = true;
                                        $refundtype = 0;
                                    }
                                    else
                                    {
                                        if( $item["paytype"] == 21 )
                                        {
                                            if( $item["apppay"] == 2 )
                                            {
                                                $result = m("finance")->wxapp_refund($item["openid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["apppay"]) ? true : false));
                                            }
                                            else
                                            {
                                                if( !empty($ispeerpay) )
                                                {
                                                    $pid = $ispeerpay["id"];
                                                    $peerpaysql = "SELECT * FROM " . tablename("ewei_shop_order_peerpay_payinfo") . " WHERE pid = :pid";
                                                    $peerpaylist = pdo_fetchall($peerpaysql, array( ":pid" => $pid ));
                                                    if( empty($peerpaylist) )
                                                    {
                                                        show_json(0, "没有人帮他代付过,无需退款");
                                                    }
                                                    foreach( $peerpaylist as $k => $v )
                                                    {
                                                        $ordersn = $item["ordersn"].'-'.$item["wechatpay"];
							$applyprice = $refund["applyprice"];
                                                        if( empty($v["tid"]) )
                                                        {
                                                            m("member")->setCredit($v["openid"], "credit2", $v["price"], array( 0, $shopset["name"] . "退款: " . $v["price"] . "元 代付订单号: " . $ordersn ));
                                                            $result = true;
                                                            continue;
                                                        }
                                                        $result = m("finance")->refund($v["openid"], $ordersn, $refund["refundno"] . $v["id"], $v["price"] * 100, $applyprice * 100, (!empty($item["apppay"]) ? true : false));
                                                    }
                                                }
                                                else
                                                {
                                                    if( 0 < $pay_refund_price )
                                                    {
                                                        if( empty($item["isborrow"]) )
                                                        {
                                                            $result = m("finance")->refund($item["openid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["apppay"]) ? true : false));
                                                        }
                                                        else
                                                        {
                                                            $result = m("finance")->refundBorrow($item["borrowopenid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["ordersn2"]) ? 1 : 0));
                                                        }
                                                    }
                                                }
                                            }
                                            $refundtype = 2;
                                        }
                                        else
                                        {
                                            if( $item["paytype"] == 22 )
                                            {
                                                $sec = m("common")->getSec();
                                                $sec = iunserializer($sec["sec"]);
                                                if( !empty($item["apppay"]) )
                                                {
                                                    if( !empty($sec["app_alipay"]["private_key_rsa2"]) )
                                                    {
                                                        $sign_type = "RSA2";
                                                        $privatekey = $sec["app_alipay"]["private_key_rsa2"];
                                                    }
                                                    else
                                                    {
                                                        $sign_type = "RSA";
                                                        $privatekey = $sec["app_alipay"]["private_key"];
                                                    }
                                                    if( empty($privatekey) || empty($sec["app_alipay"]["appid"]) )
                                                    {
                                                        show_json(0, "支付参数错误，私钥为空或者APPID为空!");
                                                    }
                                                    $params = array( "out_request_no" => time(), "out_trade_no" => $ordersn, "refund_amount" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] );
                                                    $config = array( "app_id" => $sec["app_alipay"]["appid"], "privatekey" => $privatekey, "publickey" => "", "alipublickey" => "", "sign_type" => $sign_type );
                                                    $result = m("finance")->newAlipayRefund($params, $config);
                                                }
                                                else
                                                {
                                                    if( !empty($sec["alipay_pay"]) )
                                                    {
                                                        if( empty($sec["alipay_pay"]["private_key"]) || empty($sec["alipay_pay"]["appid"]) )
                                                        {
                                                            show_json(0, "支付参数错误，私钥为空或者APPID为空!");
                                                        }
                                                        if( $sec["alipay_pay"]["alipay_sign_type"] == 1 )
                                                        {
                                                            $sign_type = "RSA2";
                                                        }
                                                        else
                                                        {
                                                            $sign_type = "RSA";
                                                        }
                                                        $params = array( "out_request_no" => time(), "out_trade_no" => $item["ordersn"], "refund_amount" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] );
                                                        $config = array( "app_id" => $sec["alipay_pay"]["appid"], "privatekey" => $sec["alipay_pay"]["private_key"], "publickey" => "", "alipublickey" => "", "sign_type" => $sign_type );
                                                        $result = m("finance")->newAlipayRefund($params, $config);
                                                    }
                                                    else
                                                    {
                                                        if( empty($item["transid"]) )
                                                        {
                                                            show_json(0, "仅支持 升级后此功能后退款的订单!");
                                                        }
                                                        $setting = uni_setting($_W["uniacid"], array( "payment" ));
                                                        if( !is_array($setting["payment"]) )
                                                        {
                                                            return error(1, "没有设定支付参数");
                                                        }
                                                        $alipay_config = $setting["payment"]["alipay"];
                                                        $batch_no_money = $pay_refund_price * 100;
                                                        $batch_no = date("Ymd") . "RF" . $item["id"] . "MONEY" . $batch_no_money;
                                                        $res = m("finance")->AlipayRefund(array( "trade_no" => $item["transid"], "refund_price" => $pay_refund_price, "refund_reason" => $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"] ), $batch_no, $alipay_config);
                                                        if( is_error($res) )
                                                        {
                                                            show_json(0, $res["message"]);
                                                        }
                                                        show_json(1, array( "url" => $res ));
                                                    }
                                                }
                                                $refundtype = 3;
                                            }
                                            else
                                            {
                                                if( $item["paytype"] == 23 && !empty($item["isborrow"]) )
                                                {
                                                    $result = m("finance")->refundBorrow($item["borrowopenid"], $ordersn, $refund["refundno"], $order_price * 100, $pay_refund_price * 100, (!empty($item["ordersn2"]) ? 1 : 0));
                                                    $refundtype = 4;
                                                }
                                                else
                                                {
                                                    if( $pay_refund_price < 1 )
                                                    {
                                                        show_json(0, "退款金额必须大于1元，才能使用微信企业付款退款!");
                                                    }
                                                    if( 0 < $pay_refund_price )
                                                    {
                                                        $result = m("finance")->pay($item["openid"], 1, $pay_refund_price * 100, $refund["refundno"], $shopset["name"] . "退款: " . $pay_refund_price . "元 订单号: " . $item["ordersn"]);
                                                    }
                                                    $refundtype = 1;
                                                }
                                            }
                                        }
                                    }
                                    if( is_error($result) )
                                    {
                                        show_json(0, $result["message"]);
                                    }
                                    if( 0 < $goods["isfullback"] )
                                    {
                                        m("order")->fullbackstop($item["id"]);
                                    }
                                    if( 0 < $dededuct__refund_price )
                                    {
                                        $item["deductcredit2"] = $dededuct__refund_price;
                                        m("order")->setDeductCredit2($item);
                                    }
                                    $change_refund["reply"] = "";
                                    $change_refund["status"] = 1;
                                    $change_refund["refundtype"] = $refundtype;
                                    $change_refund["price"] = $applyprice;
                                    $change_refund["refundtime"] = $time;
                                    if( empty($refund["operatetime"]) )
                                    {
                                        $change_refund["operatetime"] = $time;
                                    }
                                    pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $item["refundid"] ));
                                    m("order")->setGiveBalance($item["id"], 2);
                                    m("order")->setStocksAndCredits($item["id"], 2);
                                    if( $refund["orderprice"] == $refund["applyprice"] && com("coupon") && !empty($item["couponid"]) )
                                    {
                                        com("coupon")->returnConsumeCoupon($item["id"]);
                                    }
                                    pdo_update("ewei_shop_order", array( "refundstate" => 0, "status" => -1, "refundtime" => $time ), array( "id" => $item["id"], "uniacid" => $uniacid ));
                                    foreach( $goods as $g )
                                    {
                                        $salesreal = pdo_fetchcolumn("select ifnull(sum(total),0) from " . tablename("ewei_shop_order_goods") . " og " . " left join " . tablename("ewei_shop_order") . " o on o.id = og.orderid " . " where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1", array( ":goodsid" => $g["id"], ":uniacid" => $uniacid ));
                                        pdo_update("ewei_shop_goods", array( "salesreal" => $salesreal ), array( "id" => $g["id"] ));
                                    }
                                    $log = "订单退款 ID: " . $item["id"] . " 订单号: " . $item["ordersn"];
                                    if( 0 < $item["parentid"] )
                                    {
                                        $log .= " 父订单号:" . $ordersn;
                                    }
                                    plog("order.op.refund.submit", $log);
                                    m("notice")->sendOrderMessage($item["id"], true);
                                }
                                else
                                {
                                    if( $refundstatus == -1 )
                                    {
                                        pdo_update("ewei_shop_order_refund", array( "reply" => $refundcontent, "status" => -1, "endtime" => $time ), array( "id" => $item["refundid"] ));
                                        plog("order.op.refund.submit", "订单退款拒绝 ID: " . $item["id"] . " 订单号: " . $item["ordersn"] . " 原因: " . $refundcontent);
                                        pdo_update("ewei_shop_order", array( "refundstate" => 0 ), array( "id" => $item["id"], "uniacid" => $uniacid ));
                                        m("notice")->sendOrderMessage($item["id"], true);
                                    }
                                    else
                                    {
                                        if( $refundstatus == 2 )
                                        {
                                            $refundtype = 2;
                                            $change_refund["reply"] = "";
                                            $change_refund["status"] = 1;
                                            $change_refund["refundtype"] = $refundtype;
                                            $change_refund["price"] = $refund["applyprice"];
                                            $change_refund["refundtime"] = $time;
                                            if( empty($refund["operatetime"]) )
                                            {
                                                $change_refund["operatetime"] = $time;
                                            }
                                            pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $item["refundid"] ));
                                            m("order")->setGiveBalance($item["id"], 2);
                                            m("order")->setStocksAndCredits($item["id"], 2);
                                            if( $refund["orderprice"] == $refund["applyprice"] && com("coupon") && !empty($item["couponid"]) )
                                            {
                                                com("coupon")->returnConsumeCoupon($item["id"]);
                                            }
                                            pdo_update("ewei_shop_order", array( "refundstate" => 0, "status" => -1, "refundtime" => $time ), array( "id" => $item["id"], "uniacid" => $uniacid ));
                                            $goods = pdo_fetchall("SELECT g.id,g.credit, o.total,o.realprice FROM " . tablename("ewei_shop_order_goods") . " o left join " . tablename("ewei_shop_goods") . " g on o.goodsid=g.id " . " WHERE o.orderid=:orderid and o.uniacid=:uniacid", array( ":orderid" => $item["id"], ":uniacid" => $uniacid ));
                                            $credits = m("order")->getGoodsCredit($goods);
                                            plog("order.op.refund.submit", "订单退款 ID: " . $item["id"] . " 订单号: " . $item["ordersn"] . " 手动退款!");
                                            if( $item["status"] == 3 && 0 < $credits )
                                            {
                                                m("member")->setCredit($item["openid"], "credit1", 0 - $credits, array( 0, $shopset["name"] . "退款扣除购物赠送积分: " . $credits . " 订单号: " . $item["ordersn"] ));
                                            }
                                            foreach( $goods as $g )
                                            {
                                                $salesreal = pdo_fetchcolumn("select ifnull(sum(total),0) from " . tablename("ewei_shop_order_goods") . " og " . " left join " . tablename("ewei_shop_order") . " o on o.id = og.orderid " . " where og.goodsid=:goodsid and o.status>=1 and o.uniacid=:uniacid limit 1", array( ":goodsid" => $g["id"], ":uniacid" => $uniacid ));
                                                pdo_update("ewei_shop_goods", array( "salesreal" => $salesreal ), array( "id" => $g["id"] ));
                                            }
                                            m("notice")->sendOrderMessage($item["id"], true);
                                        }
                                    }
                                }
                            }
                        }
				    }
			    }
			    show_json(1);
		    }
        }
        $refundids = intval($_GPC["refundid"]);
        $refund2 = pdo_fetch("select id,orderid,goodsid,refundnum from " . tablename("ewei_shop_order_refund") . " where id=:id limit 1", array( ":id" => $refundids ));

		$refund_address = pdo_fetchall("select * from " . tablename("ewei_shop_refund_address") . " where uniacid=:uniacid and merchid=0", array( ":uniacid" => $_W["uniacid"] ));
		$express_list = m("express")->getExpressList();
		include($this->template());
	}

	public function main() 
	{
		global $_W;
		global $_GPC;
		global $_S;
		$opdata = $this->opData();
		extract($opdata);
		$step_array = array( );
		$step_array[1]["step"] = 1;
		$step_array[1]["title"] = "客户申请维权";
		$step_array[1]["time"] = $refund["createtime"];
		$step_array[1]["done"] = 1;
		$step_array[2]["step"] = 2;
		$step_array[2]["title"] = "商家处理维权申请";
		$step_array[2]["done"] = 1;
		$step_array[3]["step"] = 3;
		$step_array[3]["done"] = 0;
		if( 0 <= $refund["status"] ) 
		{
			if( $refund["rtype"] == 0 ) 
			{
				$step_array[3]["title"] = "退款完成";
			}
			else 
			{
				if( $refund["rtype"] == 1 ) 
				{
					$step_array[3]["title"] = "客户退回物品";
					$step_array[4]["step"] = 4;
					$step_array[4]["title"] = "退款退货完成";
				}
				else 
				{
					if( $refund["rtype"] == 2 ) 
					{
						$step_array[3]["title"] = "客户退回物品";
						$step_array[4]["step"] = 4;
						$step_array[4]["title"] = "商家重新发货";
						$step_array[5]["step"] = 5;
						$step_array[5]["title"] = "换货完成";
					}
				}
			}
			if( $refund["status"] == 0 ) 
			{
				$step_array[2]["done"] = 0;
				$step_array[3]["done"] = 0;
			}
			if( $refund["rtype"] == 0 ) 
			{
				if( 0 < $refund["status"] ) 
				{
					$step_array[2]["time"] = $refund["refundtime"];
					$step_array[3]["done"] = 1;
					$step_array[3]["time"] = $refund["refundtime"];
				}
			}
			else 
			{
				$step_array[2]["time"] = $refund["operatetime"];
				if( $refund["status"] == 1 || 4 <= $refund["status"] ) 
				{
					$step_array[3]["done"] = 1;
					$step_array[3]["time"] = $refund["sendtime"];
				}
				if( $refund["status"] == 1 || $refund["status"] == 5 ) 
				{
					$step_array[4]["done"] = 1;
					if( $refund["rtype"] == 1 ) 
					{
						$step_array[4]["time"] = $refund["refundtime"];
					}
					else 
					{
						if( $refund["rtype"] == 2 ) 
						{
							$step_array[4]["time"] = $refund["returntime"];
							if( $refund["status"] == 1 ) 
							{
								$step_array[5]["done"] = 1;
								$step_array[5]["time"] = $refund["refundtime"];
							}
						}
					}
				}
			}
		}
		else 
		{
			if( $refund["status"] == -1 ) 
			{
				$step_array[2]["done"] = 1;
				$step_array[2]["time"] = $refund["endtime"];
				$step_array[3]["done"] = 1;
				$step_array[3]["title"] = "拒绝" . $r_type[$refund["rtype"]];
				$step_array[3]["time"] = $refund["endtime"];
			}
			else 
			{
				if( $refund["status"] == -2 ) 
				{
					if( !empty($refund["operatetime"]) ) 
					{
						$step_array[2]["done"] = 1;
						$step_array[2]["time"] = $refund["operatetime"];
					}
					$step_array[3]["done"] = 1;
					$step_array[3]["title"] = "客户取消" . $r_type[$refund["rtype"]];
					$step_array[3]["time"] = $refund["refundtime"];
				}
			}
		}
		$goods = pdo_fetchall("SELECT g.*, o.goodssn as option_goodssn, o.productsn as option_productsn,o.total,g.type,o.optionname,o.optionid,o.price as orderprice,o.realprice,o.changeprice,o.oldprice,o.commission1,o.commission2,o.commission3,o.commissions " . $diyformfields . " FROM " . tablename("ewei_shop_order_goods") . " o left join " . tablename("ewei_shop_goods") . " g on o.goodsid=g.id " . " WHERE o.orderid=:orderid and o.uniacid=:uniacid", array( ":orderid" => $id, ":uniacid" => $_W["uniacid"] ));
		foreach( $goods as &$r ) 
		{
			if( !empty($r["option_goodssn"]) ) 
			{
				$r["goodssn"] = $r["option_goodssn"];
			}
			if( !empty($r["option_productsn"]) ) 
			{
				$r["productsn"] = $r["option_productsn"];
			}
			if( p("diyform") ) 
			{
				$r["diyformfields"] = iunserializer($r["diyformfields"]);
				$r["diyformdata"] = iunserializer($r["diyformdata"]);
			}
		}
		unset($r);
		$item["goods"] = $goods;

		$member = m("member")->getMember($item["openid"]);
		$express_list = m("express")->getExpressList();
		include($this->template());
	}

	
}
?>