<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Erprefund_EweiShopV2Model{
	protected $refundidArr = [];

	protected function globalData($orderid){
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid));
        $orderprice = $order['price'];

        if( $order['iscycelbuy'] == 1 ){
            //查询分期订单下面是否存在有开始的周期商品
            $order_goods = pdo_fetch( "select * from ".tablename( 'ewei_shop_cycelbuy_periods' )."where orderid = {$order['id']} and status != 0" );
            if( !empty($order_goods) ){
                show_json( 0 , '订单已经开始，无法进行退款' );
            }
        }


        if (empty($order)) {
                $this->message("未找到订单!", "", "error");
        }

        $_err = '';
        if ($order['status'] == 0) {
            $_err = '订单未付款，不能申请退款!';
        } else {
            if ($order['status'] == 3) {
                if (!empty($order['virtual']) || $order['isverify'] == 1) {
                    $_err = '此订单不允许退款!';
                } else {
                    if ($order['refundstate'] == 0) {
                        //申请退款
                        $tradeset = m('common')->getSysset('trade');
                        $refunddays = intval($tradeset['refunddays']);
                        if ($refunddays > 0) {
                            $days = intval((time() - $order['finishtime']) / 3600 / 24);
                            if ($days > $refunddays) {
                                $_err = '订单完成已超过 ' . $refunddays . ' 天, 无法发起退款申请!';
                            }
                        } else {
                            $_err = '订单完成, 无法申请退款!';
                        }
                    }
                }
            }
        }

        if (!empty($_err)) {
            if ($_W['isajax']) {
                show_json(0, $_err);
            } else {
                $this->message($_err, '', 'error');
            }
        }


        //订单不能退货商品
        /*********************************************************************/
        $order['cannotrefund'] = false;

        if($order['status']==2){
            $goods = pdo_fetchall("select og.goodsid, og.price, og.total, og.optionname, g.cannotrefund, g.thumb, g.title,g.isfullback from".tablename("ewei_shop_order_goods") ." og left join ".tablename("ewei_shop_goods")." g on g.id=og.goodsid where og.orderid=".$order['id']);
            if(!empty($goods)){
                foreach ($goods as $g){
                    if($g['cannotrefund']==1){
                        $order['cannotrefund'] = true;
                        break;
                    }
                }
            }
        }
        if($order['cannotrefund']){
            $this->message("此订单不可退换货");
        }

        //是否全返商品，并检测是否允许退款
        $fullback_log = pdo_fetch("select * from ".tablename('ewei_shop_fullback_log')." where orderid = ".$orderid." and uniacid = ".$uniacid." ");
        if($fullback_log){
            $fullbackgoods = pdo_fetch("select refund from ".tablename('ewei_shop_fullback_goods')." where goodsid = ".$fullback_log['goodsid']." and uniacid = ".$uniacid." ");
            if($fullback_log['fullbackday']>0){
                if($fullback_log['fullbackday']<$fullback_log['day']){
                    $order['price'] = $order['price'] - $fullback_log['priceevery'] * $fullback_log['fullbackday'];
                }else{
                    $order['price'] = $order['price'] - $fullback_log['price'];
                }
            }
        }


        //应该退的钱 在线支付的+积分抵扣的+余额抵扣的(运费包含在在线支付或余额里）
        $order['refundprice'] = $order['price'] + $order['deductcredit2'];
        if ($order['status'] >= 2) {
            //如果发货，扣除运费
            $order['refundprice']-= $order['dispatchprice'];
        }
        $order['refundprice'] = round($order['refundprice'],2);

        //获取漏发需要退款的商品  存入到数组中
        $orderGoodsAll = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_goods") . " WHERE uniacid=:uniacid and orderid=:orderid", array(":uniacid" => $_W["uniacid"], ":orderid"=> $orderid));
        $refundAll = pdo_fetchall("SELECT goodsid,refundnum FROM " . tablename("ewei_shop_order_refund") . " WHERE uniacid=:uniacid and orderid=:orderid", array(":uniacid" => $_W["uniacid"], ":orderid"=> $orderid));
        if(!empty($refundAll)){
        	foreach($refundAll as $v){
        		$refundids[] = $v['goodsid'];
        		$refundats[$v['goodsid']] = $v['refundnum'];
        	}
        }

        $erprefund = '';
        foreach($orderGoodsAll as $k => $v){
           if($v['total'] != $v['export_num'] && $v['export_num'] > 0){
           	   $number = $v['total'] - $v['export_num'];
           	   $orderGetGoods = pdo_fetch("select marketprice from ".tablename("ewei_shop_goods")." where id =:goodsid limit 1",array(":goodsid" => $v['goodsid']));
             $price = $orderGetGoods['marketprice'];
             $erprefund[$k] = array('orderid'=>$v['orderid'],'goodsid'=>$v['goodsid'],'refundnum'=>$number,'price'=>$price);

           }
        }

        return array(
            'uniacid' => $uniacid,
            'openid' => $_W['openid'],
            'order' => $order,
            'refundid' => $order['refundid'],
            'fullback_log'=>$fullback_log,
            'fullbackgoods'=>$fullbackgoods,
            'orderprice'=>$orderprice,
            'erprefund' => $erprefund,
            'refundids' => $refundids,
            'refundats' => $refundats,
        );
    }


    //退款记录新建
	function refundinto($orderid){
        global $_W, $_GPC;
        $uniacid = $_W['uniacid'];
        extract($this->globalData($orderid));
        $rtype = 0; //仅退款
        foreach($erprefund as $k => $v){
        	if(!empty($refundids)){
        		if(in_array($v['goodsid'],$refundids)){
        			if($refundats[$v['goodsid']] >= $v['refundnum']){
        				continue;
        			}else{
                       $v['refundnum'] = $v['refundnum'] - $refundats[$v['goodsid']];
                       $v['price'] = $v['refundnum']*$v['price'];
        			}
        		}
        	}
	    	$refundno = m('common')->createNO('order_refund','refundno','SR');
	    	$refund = array(
	        'uniacid' => $uniacid,
	        'orderid' => $v['orderid'],
	        'applyprice' => $v['price'],
	        'refundno' => $refundno,
	        'goodsid' => $v['goodsid'],
	        'refundnum'=>$v['refundnum'],
	        'merchid' => 0,
	        'rtype' => 0,
	        'reason' => '漏发',
	        'content' => '漏发退款',
	        'refundtype' => 3,//部分退款
	        'imgs' => '',
	        'createtime' => time(),
	        'orderprice' => $orderprice,
	        );

	        pdo_insert('ewei_shop_order_refund', $refund);
	        $refundid = pdo_insertid();

	        $this->refundidArr[$k] = array('refundid' => $refundid, 'goodsid' =>$v['goodsid'],'orderid' => $v['orderid']);
	        pdo_update('ewei_shop_order_goods', array('status1' => 1), array('orderid' => $v['orderid'], 'goodsid' => $v['goodsid'], 'uniacid' => $uniacid));
        }
        $refundstate = 1;//更改订单表退款状态
        $ispartrefund = 0;
        pdo_update('ewei_shop_order', array('refundid' => $refundid, 'refundstate' => $refundstate, 'ispartrefund' => $ispartrefund, 'refundtime' => 0), array('id' => $orderid, 'uniacid' => $uniacid));
            
        //全返退款，退款退货
        if(($rtype==0 || $rtype==1) && $order['status']>=3){
            //全返管理停止
            if($fullback_log){
                m('order')->fullbackstop($orderid);
            }
        }
       return 1;
    }

    //漏发退款 20190712
    public function omitRefund(){
       $data = $this->refundidArr;
       foreach($data as $v){
       	$ss = $this->submit($v['orderid'],$v['goodsid'],$v['refundid']);
       }
       return $ss;    
    }


    public function submit($orderid,$goodsid,$refundid)
	{
		global $_W;
		global $_GPC;
		global $_S;
		$item = pdo_fetch("SELECT * FROM " . tablename("ewei_shop_order") . " WHERE id = :id and uniacid=:uniacid Limit 1", array( ":id" => $orderid, ":uniacid" => $_W["uniacid"] ));
		$refund = pdo_fetch("select * from " . tablename("ewei_shop_order_refund") . " where id=:id limit 1", array( ":id" => $refundid ));
		$refund["imgs"] = iunserializer($refund["imgs"]);
		$r_type = array( "退款", "退货退款", "换货" );
        
        $shopset = $_S["shop"];

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

				$refundOne = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_refund") . " WHERE uniacid=:uniacid and orderid=:orderid and status>=0", array(":uniacid" => $_W["uniacid"], ":orderid"=>$orderid));
	            foreach($refundOne as $kk=>$vv){
	                $refundOne[$kk]['goods_title'] = pdo_getcolumn('ewei_shop_goods', array('id' => $vv['goodsid']), 'title');
	            }
	            $refundstatus = intval(1);
				$refundcontent = trim($_GPC["refundcontent"]);
				$time = time();
				$change_refund = array( );
				$uniacid = $_W["uniacid"];

				if( $refundstatus == 0 )
				{
					show_json(1);
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

						pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
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
							pdo_update("ewei_shop_order_goods", $goods_data, array( "goodsid" => $goodsid, "uniacid" => $uniacid ));
							m("notice")->sendOrderMessage($item["id"], true);
							if($irem['goodsprice'] == sum($refundOne['applyprice'])){
								pdo_update("ewei_shop_order", array('status' => -1), array( "id" => $item['id'], "uniacid" => $uniacid));
							}

						    }else{

								if( $refundstatus == 1 )
								{
									if( 0 < $item["parentid"] )
									{
									$parent_item = pdo_fetch("SELECT id,ordersn,ordersn2,price,transid,paytype,apppay,wechatpay FROM " . tablename("ewei_shop_order") . " WHERE id = :id and uniacid=:uniacid Limit 1", array( ":id" => $item["parentid"], ":uniacid" => $_W["uniacid"] ));
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
								    }else{

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
										{   $time = time();
											pdo_update("ewei_shop_order", array('remarksaler' => '漏发退款','refundtime'=> $time,'refundstate' => '-1'), array( "id" => $item['id'], "uniacid" => $uniacid));
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
													if( empty($v["tid"]) )
													{
														m("member")->setCredit($v["openid"], "credit2", $v["price"], array( 0, $shopset["name"] . "退款: " . $v["price"] . "元 代付订单号: " . $item["ordersn"] ));
														$result = true;
														continue;
													}
													$result = m("finance")->refund($v["openid"], $v["tid"], $refund["refundno"] . $v["id"], $v["price"] * 100, $v["price"] * 100, (!empty($item["apppay"]) ? true : false));
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
								pdo_update("ewei_shop_order_refund", $change_refund, array( "id" => $refundid ));
								$goods_data = array( );
							    $goods_data["status1"] = 3;
							    pdo_update("ewei_shop_order_goods", $goods_data, array( "goodsid" => $goodsid, "uniacid" => $uniacid ));
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
							        pdo_update("ewei_shop_order_goods", $goods_data, array( "goodsid" => $goodsid, "orderid" => $orderid));
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
										pdo_update("ewei_shop_order_goods", $goods_data, array( "goodsid" => $goodsid, "uniacid" => $uniacid ));
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
			}

}


            
	public function refundOneGoods($orderid) 
	{
	 	global $_W;
	 	global $_GPC;
		$refundOne = pdo_fetchall("SELECT * FROM " . tablename("ewei_shop_order_refund") . " WHERE uniacid=:uniacid and orderid=:orderid and status>=0", array(":uniacid" => $_W["uniacid"], ":orderid"=> $orderid));
		$time = time();
		if(!empty($refundOne)){
			$num = count($refundOne);
			if($num == 1){
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
}
    
