<?php
if (!(defined("IN_IA"))) 
{
	exit("Access Denied");
}
require EWEI_SHOPV2_PLUGIN . "pc/core/page_login_mobile.php";
class Index_EweiShopV2Page extends PcMobileLoginPage 
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

	protected function merchData() 
	{
		$merch_plugin = p('merch');
		$merch_data = m('common')->getPluginset('merch');
		if ($merch_plugin && $merch_data['is_openmerch']) 
		{
			$is_openmerch = 1;
		}
		else 
		{
			$is_openmerch = 0;
		}
		return array("is_openmerch" => $is_openmerch, 'merch_plugin' => $merch_plugin, 'merch_data' => $merch_data);
	}
	public function main() 
	{
		global $_W;
		global $_GPC;
		$trade = m('common')->getSysset('trade');
        $member = $this->member;
		$merchdata = $this->merchData();
		extract($merchdata);
		$nav_link_list = array( array('link' => mobileUrl('pc'), 'title' => '首页'), array('link' => mobileUrl('pc.member'), 'title' => '我的商城'), array('title' => '交易订单') );
		$ice_menu_array = array( array('menu_key' => 'index', 'menu_name' => '订单列表', 'menu_url' => mobileUrl('pc.order')) );
		$all_list = $this->get_list();
		$list = $all_list['list'];

		$pindex = max(1, intval($_GPC['page']));
		$pager = fenye($all_list['total'], $pindex, $all_list['psize']);
		include $this->template();
	}

    public function get_list()
    {
        global $_W;
        global $_GPC;
        $uniacid = $_W['uniacid'];
        $openid = $_W['openid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 10;
        $show_status = $_GPC['status'];
        $r_type = array('退款', '退货退款', '换货');
        $condition = ' and openid=:openid and ismr=0 and deleted=0 and uniacid=:uniacid ';
        $params = array(':uniacid' => $uniacid, ':openid' => $openid);
        $merchdata = $this->merchData();
        extract($merchdata);
        $condition .= ' and merchshow=0 ';

        if ($show_status != '') {
            $show_status =intval($show_status);

            switch ($show_status)
            {
                case -2:
                    $condition .= ' and status=0 and paytype!=3';
                    break;
                case 0:
                    $condition.=' and status=0 and paytype!=3';
                    break;
                case 1:
                    $condition.=' and (status=1 or (status=0 and paytype=3))';
                    break;
                case 2:
                    $condition.=' and (status=2 or (status=1 and sendtype>0))';
                    break;
                case 4:
                    $condition.=' and refundstate>0';
                    break;
                case 5:
                    $condition .= " and userdeleted=1 ";
                    break;
                default:
                    $condition.=' and status=' . intval($show_status);
            }

            if ($show_status != 5) {
                $condition .= " and userdeleted=0 ";
            }
        } else {
            $condition .= " and userdeleted=0 ";
        }

        $order_sn_search = $_GPC['order_sn'];
        if (!(empty($order_sn_search)))
        {
            $condition .= ' and ordersn LIKE \'%' . $order_sn_search . '%\' ';
        }
        $query_start_date = $_GPC['start_date'];
        $query_end_date = $_GPC['end_date'];
        if (!(empty($query_start_date)))
        {
            $query_start_date = strtotime($query_start_date);
            $condition .= ' AND createtime >= ' . $query_start_date;
        }
        if (!(empty($query_end_date)))
        {
            $query_end_date = strtotime($query_end_date);
            $condition .= ' AND createtime <=  ' . $query_end_date;
        }

        $com_verify = com('verify');
        $list = pdo_fetchall('select id,btype,isbill,createtime,addressid,ordersn,price,dispatchprice,status,iscomment,isverify,' . "\n" . 'verified,verifycode,verifytype,iscomment,refundid,expresscom,express,expresssn,finishtime,`virtual`,' . "\n" . 'paytype,expresssn,refundstate,dispatchtype,verifyinfo,merchid,isparent,userdeleted' . "\n" . ' from ' . tablename('ewei_shop_order') . ' where 1 ' . $condition . ' order by createtime desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where 1 ' . $condition, $params);
        $refunddays = intval($_W['shopset']['trade']['refunddays']);
        if ($is_openmerch == 1)
        {
            $merch_user = $merch_plugin->getListUser($list, 'merch_user');
        }
        foreach ($list as &$row )
        {
            $param = array();
            if ($row['isparent'] == 1)
            {
                $scondition = ' og.parentorderid=:parentorderid';
                $param[':parentorderid'] = $row['id'];
            }
            else
            {
                $scondition = ' og.orderid=:orderid';
                $param[':orderid'] = $row['id'];
            }
            $sql = 'SELECT og.goodsid,og.total,g.title,g.thumb,og.oldprice as price,og.optionname as optiontitle,og.optionid,op.specs,og.export_num,og.erp_trade_no,og.merchid FROM ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id ' . ' left join ' . tablename('ewei_shop_goods_option') . ' op on og.optionid = op.id ' . ' where ' . $scondition . ' order by og.id asc';
            $goods = pdo_fetchall($sql, $param);
            foreach ($goods as &$r )
            {
                if($r['merchid'] == 0){
                    $r['merchname'] = '集和堂医药';
                }else{
                    $r['merchname'] = pdo_getcolumn('ewei_shop_merch_user', array('id' => $r['merchid']), 'merchname');
                }

                if (!(empty($r['specs'])))
                {
                    $thumb = m('goods')->getSpecThumb($r['specs']);
                    if (!(empty($thumb)))
                    {
                        $r['thumb'] = $thumb;
                    }
                }
            }
            unset($r);
            $row['goods'] = set_medias($goods, 'thumb');
            foreach ($row['goods'] as &$r )
            {
                $r['thumb'] .= '?t=' . random(50);
            }
            unset($r);
            $statuscss = 'text-cancel';
            switch ($row['status'])
            {
                case '-1': $status = '已取消';
                    break;
                case '0': if ($row['paytype'] == 3)
                {
                    $status = '待发货';
                }
                else
                {
                    $status = '待付款';
                }
                    $statuscss = 'text-cancel';
                    break;
                case '1': if ($row['isverify'] == 1)
                {
                    $status = '使用中';
                }
                else if (empty($row['addressid']))
                {
                    $status = '待取货';
                }
                else
                {
                    $status = '待发货';
                }
                    $statuscss = 'text-warning';
                    break;
                case '2': $status = '待收货';
                    $statuscss = 'text-danger';
                    break;
                case '3': if (empty($row['iscomment']))
                {
                    if ($show_status == 5)
                    {
                        $status = '已完成';
                    }
                    else
                    {
                        $status = ((empty($_W['shopset']['trade']['closecomment']) ? '待评价' : '已完成'));
                    }
                }
                else
                {
                    $status = '交易完成';
                }
                    $statuscss = 'text-success';
            }
            $row['statusstr'] = $status;
            $row['statuscss'] = $statuscss;
            if ((0 < $row['refundstate']) && !(empty($row['refundid'])))
            {
                $refund = pdo_fetch('select * from ' . tablename('ewei_shop_order_refund') . ' where id=:id and uniacid=:uniacid and orderid=:orderid limit 1', array(':id' => $row['refundid'], ':uniacid' => $uniacid, ':orderid' => $row['id']));
                if (!(empty($refund)))
                {
                    $row['statusstr'] = '待' . $r_type[$refund['rtype']];
                }
            }
            $canrefund = false;
            $row['canrefund'] = $canrefund;
            $row['canverify'] = false;
            $canverify = false;
            if ($com_verify)
            {
                $showverify = $row['dispatchtype'] || $row['isverify'];
                if ($row['isverify'])
                {
                    if (($row['verifytype'] == 0) || ($row['verifytype'] == 1))
                    {
                        $vs = iunserializer($row['verifyinfo']);
                        $verifyinfo = array( array('verifycode' => $row['verifycode'], 'verified' => ($row['verifytype'] == 0 ? $row['verified'] : $row['goods'][0]['total'] <= count($vs))) );
                        if ($row['verifytype'] == 0)
                        {
                            $canverify = empty($row['verified']) && $showverify;
                        }
                        else if ($row['verifytype'] == 1)
                        {
                            $canverify = (count($vs) < $row['goods'][0]['total']) && $showverify;
                        }
                    }
                    else
                    {
                        $verifyinfo = iunserializer($row['verifyinfo']);
                        $last = 0;
                        foreach ($verifyinfo as $v )
                        {
                            if (!($v['verified']))
                            {
                                ++$last;
                            }
                        }
                        $canverify = (0 < $last) && $showverify;
                    }
                }
                else if (!(empty($row['dispatchtype'])))
                {
                    $canverify = ($row['status'] == 1) && $showverify;
                }
            }
            $row['canverify'] = $canverify;
            if ($is_openmerch == 1)
            {
                $row['merchname'] = (($merch_user[$row['merchid']]['merchname'] ? $merch_user[$row['merchid']]['merchname'] : $_W['shopset']['shop']['name']));
            }


            //周期付款期限
            $row['btype_time'] = 0;
            if($row['btype'] == 2){
                $member = m('member')->getMember($openid);
                $t = round((time() - $row['createtime']) / 86400);
//                if($t < $member['branch_day']){
//                    $row['btype_time'] = ($member['branch_day'] - $t);
//                }
                if(!empty($member['branch_day']) && $t < unserialize($member['branch_day'])['days']){
                    $row['btype_time'] = (unserialize($member['branch_day'])['days'] - $t);
                }
            }
        }

        unset($row);
        return array("list" => $list, 'total' => $total, 'psize' => $psize);
    }

    //原PC端方法
//	public function get_list()
//	{
//		global $_W;
//		global $_GPC;
//		$uniacid = $_W['uniacid'];
//		$openid = $_W['openid'];
//		$pindex = max(1, intval($_GPC['page']));
//		$psize = 10;
//		$show_status = $_GPC['status'];
//		$r_type = array('退款', '退货退款', '换货');
//		$condition = ' and openid=:openid and ismr=0 and deleted=0 and uniacid=:uniacid ';
//		$params = array(':uniacid' => $uniacid, ':openid' => $openid);
//		$merchdata = $this->merchData();
//		extract($merchdata);
//		$condition .= ' and merchshow=0 ';
//		$show_status = intval($show_status);
//		switch ($show_status)
//		{
//			case -2:
//			    $condition .= ' and status=0 and paytype!=3';
//			    break;
//			case 0:
//			    if ($_GPC['mk'] == 'recycle')
//			    {
//				    $condition .= ' ';
//			    } else
//                {
//                    $condition .= ' and status!=-1 ';
//                }
//			    break;
//			case 2:
//			    $condition .= ' and (status=2 or status=0 and paytype=3)';
//			    break;
//			case 4:
//			    $condition .= ' and refundstate>0';
//			    break;
//			case 5:
//			    $condition .= ' and userdeleted=1 ';
//			    break;
//			$condition .= ' and status=' . intval($show_status);
//			goto label85;
//			label85: if ($_GPC['mk'] == 'recycle')
//			{
//				$condition .= ' and userdeleted=1 ';
//			}
//			else if ($show_status != 5)
//			{
//				$condition .= ' and userdeleted=0 ';
//			}
//			$order_sn_search = $_GPC['order_sn'];
//			if (!(empty($order_sn_search)))
//			{
//				$condition .= ' and ordersn LIKE \'%' . $order_sn_search . '%\' ';
//			}
//			$query_start_date = $_GPC['start_date'];
//			$query_end_date = $_GPC['end_date'];
//			if (!(empty($query_start_date)))
//			{
//				$query_start_date = strtotime($query_start_date);
//				$condition .= ' AND createtime >= ' . $query_start_date;
//			}
//			if (!(empty($query_end_date)))
//			{
//				$query_end_date = strtotime($query_end_date);
//				$condition .= ' AND createtime <=  ' . $query_end_date;
//			}
//			$com_verify = com('verify');
//			$list = pdo_fetchall('select id,addressid,ordersn,price,dispatchprice,status,iscomment,isverify,' . "\n" . 'verified,verifycode,verifytype,iscomment,refundid,expresscom,express,expresssn,finishtime,`virtual`,' . "\n" . 'paytype,expresssn,refundstate,dispatchtype,verifyinfo,merchid,isparent,userdeleted' . "\n" . ' from ' . tablename('ewei_shop_order') . ' where 1 ' . $condition . ' order by createtime desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
//			$total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where 1 ' . $condition, $params);
//			$refunddays = intval($_W['shopset']['trade']['refunddays']);
//			if ($is_openmerch == 1)
//			{
//				$merch_user = $merch_plugin->getListUser($list, 'merch_user');
//			}
//			foreach ($list as &$row )
//			{
//				$param = array();
//				if ($row['isparent'] == 1)
//				{
//					$scondition = ' og.parentorderid=:parentorderid';
//					$param[':parentorderid'] = $row['id'];
//				}
//				else
//				{
//					$scondition = ' og.orderid=:orderid';
//					$param[':orderid'] = $row['id'];
//				}
//				$sql = 'SELECT og.goodsid,og.total,g.title,g.thumb,og.price,og.optionname as optiontitle,og.optionid,op.specs FROM ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on og.goodsid = g.id ' . ' left join ' . tablename('ewei_shop_goods_option') . ' op on og.optionid = op.id ' . ' where ' . $scondition . ' order by og.id asc';
//				$goods = pdo_fetchall($sql, $param);
//				foreach ($goods as &$r )
//				{
//					if (!(empty($r['specs'])))
//					{
//						$thumb = m('goods')->getSpecThumb($r['specs']);
//						if (!(empty($thumb)))
//						{
//							$r['thumb'] = $thumb;
//						}
//					}
//				}
//				unset($r);
//				$row['goods'] = set_medias($goods, 'thumb');
//				foreach ($row['goods'] as &$r )
//				{
//					$r['thumb'] .= '?t=' . random(50);
//				}
//				unset($r);
//				$statuscss = 'text-cancel';
//				switch ($row['status'])
//				{
//					case '-1': $status = '已取消';
//					break;
//					case '0': if ($row['paytype'] == 3)
//					{
//						$status = '待发货';
//					}
//					else
//					{
//						$status = '待付款';
//					}
//					$statuscss = 'text-cancel';
//					break;
//					case '1': if ($row['isverify'] == 1)
//					{
//						$status = '使用中';
//					}
//					else if (empty($row['addressid']))
//					{
//						$status = '待取货';
//					}
//					else
//					{
//						$status = '待发货';
//					}
//					$statuscss = 'text-warning';
//					break;
//					case '2': $status = '待收货';
//					$statuscss = 'text-danger';
//					break;
//					case '3': if (empty($row['iscomment']))
//					{
//						if ($show_status == 5)
//						{
//							$status = '已完成';
//						}
//						else
//						{
//							$status = ((empty($_W['shopset']['trade']['closecomment']) ? '待评价' : '已完成'));
//						}
//					}
//					else
//					{
//						$status = '交易完成';
//					}
//					$statuscss = 'text-success';
//				}
//				$row['statusstr'] = $status;
//				$row['statuscss'] = $statuscss;
//				if ((0 < $row['refundstate']) && !(empty($row['refundid'])))
//				{
//					$refund = pdo_fetch('select * from ' . tablename('ewei_shop_order_refund') . ' where id=:id and uniacid=:uniacid and orderid=:orderid limit 1', array(':id' => $row['refundid'], ':uniacid' => $uniacid, ':orderid' => $row['id']));
//					if (!(empty($refund)))
//					{
//						$row['statusstr'] = '待' . $r_type[$refund['rtype']];
//					}
//				}
//				$canrefund = false;
//				$row['canrefund'] = $canrefund;
//				$row['canverify'] = false;
//				$canverify = false;
//				if ($com_verify)
//				{
//					$showverify = $row['dispatchtype'] || $row['isverify'];
//					if ($row['isverify'])
//					{
//						if (($row['verifytype'] == 0) || ($row['verifytype'] == 1))
//						{
//							$vs = iunserializer($row['verifyinfo']);
//							$verifyinfo = array( array('verifycode' => $row['verifycode'], 'verified' => ($row['verifytype'] == 0 ? $row['verified'] : $row['goods'][0]['total'] <= count($vs))) );
//							if ($row['verifytype'] == 0)
//							{
//								$canverify = empty($row['verified']) && $showverify;
//							}
//							else if ($row['verifytype'] == 1)
//							{
//								$canverify = (count($vs) < $row['goods'][0]['total']) && $showverify;
//							}
//						}
//						else
//						{
//							$verifyinfo = iunserializer($row['verifyinfo']);
//							$last = 0;
//							foreach ($verifyinfo as $v )
//							{
//								if (!($v['verified']))
//								{
//									++$last;
//								}
//							}
//							$canverify = (0 < $last) && $showverify;
//						}
//					}
//					else if (!(empty($row['dispatchtype'])))
//					{
//						$canverify = ($row['status'] == 1) && $showverify;
//					}
//				}
//				$row['canverify'] = $canverify;
//				if ($is_openmerch == 1)
//				{
//					$row['merchname'] = (($merch_user[$row['merchid']]['merchname'] ? $merch_user[$row['merchid']]['merchname'] : $_W['shopset']['shop']['name']));
//				}
//			}
//		}
//		unset($row);
//		return array("list" => $list, 'total' => $total, 'psize' => $psize);
//	}



	public function alipay() 
	{
		global $_W;
		global $_GPC;
		$url = urldecode($_GPC['url']);
		if (!(is_weixin())) 
		{
			header("location: " . $url);
			exit();
		}
		include $this->template();
	}

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

    public function refundsubmit(){
        global $_W;
        global $_GPC;
        global $_S;
        $opdata = $this->opData();
        extract($opdata);
        if( $_W["ispost"] ) 
        {
            $goodsid = $refund['goodsid'];
            $shopset = $_S["shop"];
            if( empty($item["refundstate"]) ) 
            {
                show_json(0, "订单没有退款记录，不需处理！");
            }
            if( $refund["status"] < 0 || $refund["status"] == 1 ) 
            {
                pdo_update("ewei_shop_order", array( "refundstate" => 0 ), array( "id" => $item["id"], "uniacid" => $_W["uniacid"] ));
                show_json(0, "未找需要处理的退款申请，不需处理！");
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
                                        show_json(0, "退款申请的金额错误.请联系商家处理!");
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
        $refund_address = pdo_fetchall("select * from " . tablename("ewei_shop_refund_address") . " where uniacid=:uniacid and merchid=0", array( ":uniacid" => $_W["uniacid"] ));
        $express_list = m("express")->getExpressList();
    }

	public function detail() 
	{
		global $_W;
		global $_GPC;
		$openid = $_W['openid'];
		$uniacid = $_W['uniacid'];
        $member = $this->member;
		$orderid = intval($_GPC['id']);
		if (empty($orderid)) 
		{
			header('location: ' . mobileUrl('pc.order'));
			exit();
		}
		$nav_link_list = array( array('link' => mobileUrl('pc'), 'title' => '首页'), array('link' => mobileUrl('pc.member'), 'title' => '我的商城'), array('link' => mobileUrl('pc.order'), 'title' => '交易订单'), array('title' => '订单详情') );
		$ice_menu_array = array( array('menu_key' => 'order', 'menu_name' => '订单列表', 'menu_url' => mobileUrl('pc.order')), array('menu_key' => 'index', 'menu_name' => '订单详情', 'menu_url' => mobileUrl('pc.order', array('id' => $orderid))) );
        $refundtype = pdo_fetch("select * from " . tablename('ewei_shop_order_refund') . ' where orderid=:orderid limit 1', array(':orderid' => $orderid));
		$order = pdo_fetch('select * from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));
        
		if (empty($order)) 
		{
			header('location: ' . mobileUrl('pc.order'));
			exit();
		}
		if ($order['merchshow'] == 1) 
		{
			header('location: ' . mobileUrl('pc.order'));
			exit();
		}
		if ($order['userdeleted'] == 2) 
		{
			$this->message('订单已经被删除!', '', 'error');
		}
		$merchdata = $this->merchData();
		extract($merchdata);
		$merchid = $order['merchid'];
		$diyform_plugin = p('diyform');
		$diyformfields = '';
		if ($diyform_plugin) 
		{
			$diyformfields = ',og.diyformfields,og.diyformdata';
		}
		$param = array();
		$param[':uniacid'] = $_W['uniacid'];
		if ($order['isparent'] == 1) 
		{
			$scondition = ' og.parentorderid=:parentorderid';
			$param[':parentorderid'] = $orderid;
		}
		else 
		{
			$scondition = ' og.orderid=:orderid';
			$param[':orderid'] = $orderid;
		}
		$goods = pdo_fetchall('select og.goodsid,og.oldprice as price,og.status1,g.title,g.thumb,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,g.storeids,og.export_num,og.erp_trade_no,og.merchid' . $diyformfields . '  from ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid ' . ' where ' . $scondition . ' and og.uniacid=:uniacid ', $param);
		if (!(empty($goods))) 
		{
			foreach ($goods as &$g ) 
			{
				if (!(empty($g['optionid']))) 
				{
					$thumb = m('goods')->getOptionThumb($g['goodsid'], $g['optionid']);
					if (!(empty($thumb))) 
					{
						$g['thumb'] = $thumb;
					}
				}

                if($g['merchid'] == 0){
                    $g['merchname'] = '集和堂医药';
                }else{
                    $g['merchname'] = pdo_getcolumn('ewei_shop_merch_user', array('id' => $g['merchid']), 'merchname');
                }

                //是否有退货数
                $refund2 = pdo_fetch("SELECT goodsid,refundnum FROM " . tablename('ewei_shop_order_refund') ." where status>0 and goodsid={$g['goodsid']} and orderid={$orderid} and refundnum>0 order by id desc");
                $g['refund2'] = $refund2;

			}
			unset($g);
		}
		$diyform_flag = 0;
		if ($diyform_plugin) 
		{
			foreach ($goods as &$g ) 
			{
				$g['diyformfields'] = iunserializer($g['diyformfields']);
				$g['diyformdata'] = iunserializer($g['diyformdata']);
				unset($g);
			}
			if (!(empty($order['diyformfields'])) && !(empty($order['diyformdata']))) 
			{
				$order_fields = iunserializer($order['diyformfields']);
				$order_data = iunserializer($order['diyformdata']);
			}
		}
		$address = false;
		if (!(empty($order['addressid']))) 
		{
			$address = iunserializer($order['address']);
			if (!(is_array($address))) 
			{
				$address = pdo_fetch('select * from  ' . tablename('ewei_shop_member_address') . ' where id=:id limit 1', array(':id' => $order['addressid']));
			}
		}
		$carrier = @iunserializer($order['carrier']);
		if (!(is_array($carrier)) || empty($carrier)) 
		{
			$carrier = false;
		}
		$store = false;
		if (!(empty($order['storeid']))) 
		{
			if (0 < $merchid) 
			{
				$store = pdo_fetch('select * from  ' . tablename('ewei_shop_merch_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));
			}
			else 
			{
				$store = pdo_fetch('select * from  ' . tablename('ewei_shop_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));
			}
		}
		$stores = false;
		$showverify = false;
		$canverify = false;
		$verifyinfo = false;
		if (com("verify")) 
		{
			$showverify = $order['dispatchtype'] || $order['isverify'];
			if ($order['isverify']) 
			{
				$storeids = array();
				foreach ($goods as $g ) 
				{
					if (!(empty($g['storeids']))) 
					{
						$storeids = array_merge(explode(',', $g['storeids']), $storeids);
					}
				}
				if (empty($storeids)) 
				{
					if (0 < $merchid) 
					{
						$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
					}
					else 
					{
						$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
					}
				}
				else if (0 < $merchid) 
				{
					$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
				}
				else 
				{
					$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
				}
				if (($order['verifytype'] == 0) || ($order['verifytype'] == 1)) 
				{
					$vs = iunserializer($order['verifyinfo']);
					$verifyinfo = array( array('verifycode' => $order['verifycode'], 'verified' => ($order['verifytype'] == 0 ? $order['verified'] : $goods[0]['total'] <= count($vs))) );
					if ($order['verifytype'] == 0) 
					{
						$canverify = empty($order['verified']) && $showverify;
					}
					else if ($order['verifytype'] == 1) 
					{
						$canverify = (count($vs) < $goods[0]['total']) && $showverify;
					}
				}
				else 
				{
					$verifyinfo = iunserializer($order['verifyinfo']);
					$last = 0;
					foreach ($verifyinfo as $v ) 
					{
						if (!($v['verified'])) 
						{
							++$last;
						}
					}
					$canverify = (0 < $last) && $showverify;
				}
			}
			else if (!(empty($order['dispatchtype']))) 
			{
				$verifyinfo = array( array('verifycode' => $order['verifycode'], 'verified' => $order['status'] == 3) );
				$canverify = ($order['status'] == 1) && $showverify;
			}
		}
		$order['canverify'] = $canverify;
		$order['showverify'] = $showverify;
		$order['virtual_str'] = str_replace("\n", '<br/>', $order['virtual_str']);
		if (($order['status'] == 1) || ($order['status'] == 2)) 
		{
			$canrefund = true;
			if (($order['status'] == 2) && ($order['price'] == $order['dispatchprice'])) 
			{
				if (0 < $order['refundstate']) 
				{
					$canrefund = true;
				}
				else 
				{
					$canrefund = false;
				}
			}
		}
		else if ($order['status'] == 3) 
		{
			if (($order['isverify'] != 1) && empty($order['virtual'])) 
			{
				if (0 < $order['refundstate']) 
				{
					$canrefund = true;
				}
				else 
				{
					$tradeset = m('common')->getSysset('trade');
					$refunddays = intval($tradeset['refunddays']);
					if (0 < $refunddays) 
					{
						$days = intval((time() - $order['finishtime']) / 3600 / 24);
						if ($days <= $refunddays) 
						{
							$canrefund = true;
						}
					}
				}
			}
		}
		$order['canrefund'] = $canrefund;
		$express = false;
		if ((2 <= $order['status']) && empty($order['isvirtual']) && empty($order['isverify'])) 
		{
			$expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);
			if (0 < count($expresslist))
			{
				$express = $expresslist[0];
			}
		}

		$shopname = $_W['shopset']['shop']['name'];
		if (!(empty($order['merchid'])) && ($is_openmerch == 1)) 
		{
			$merch_user = $merch_plugin->getListUser($order['merchid']);
			$shopname = $merch_user['merchname'];
			$shoplogo = tomedia($merch_user['logo']);
		}

        //周期付款期限
        $order['btype_time'] = 0;
        if($order['btype'] == 2){
            $t = round((time() - $order['createtime']) / 86400);
//            if($t < $member['branch_day']){
//                $order['btype_time'] = ($member['branch_day'] - $t);
//            }
            if(!empty($member['branch_day']) && $t < unserialize($member['branch_day'])['days']){
                $order['btype_time'] = (unserialize($member['branch_day'])['days'] - $t);
            }
        }

		include $this->template();
	}
	public function express() 
	{
		global $_W;
		global $_GPC;
		global $_W;
		global $_GPC;
		$openid = $_W['openid'];
		$uniacid = $_W['uniacid'];
		$orderid = intval($_GPC['id']);
		if (empty($orderid)) 
		{
			header('location: ' . mobileUrl('pc.order'));
			exit();
		}
		$order = pdo_fetch('select * from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));
		if (empty($order)) 
		{
			header('location: ' . mobileUrl('pc.order'));
			exit();
		}
		if (empty($order['addressid'])) 
		{
			$this->message('订单非快递单，无法查看物流信息!');
		}
		if ($order['status'] < 2) 
		{
			$this->message('订单未发货，无法查看物流信息!');
		}
		$goods = pdo_fetchall('select og.goodsid,og.price,g.title,g.thumb,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,g.storeids' . $diyformfields . '  from ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid ' . ' where og.orderid=:orderid and og.uniacid=:uniacid ', array(':uniacid' => $uniacid, ':orderid' => $orderid));
		$expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);
        $member = $this->member;
		include $this->template();
	}
}
?>