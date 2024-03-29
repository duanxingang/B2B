<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Index_EweiShopV2Page extends MobileLoginPage {

    //多商户 1-2-3
    protected function merchData() {
        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        return array(
            'is_openmerch' => $is_openmerch,
            'merch_plugin' => $merch_plugin,
            'merch_data' => $merch_data
        );
    }

    function main() {
        global $_W,$_GPC;
        $trade = m('common')->getSysset('trade');

        //多商户
        $merchdata = $this->merchData();
        extract($merchdata);

        if ($is_openmerch == 1) {
            include $this->template('merch/order/index');
        }else{
            include $this->template();
        }
    }

    function get_list(){

        global $_W,$_GPC;
        $uniacid =$_W['uniacid'];
        $openid =$_W['openid'];
        $pindex = max(1, intval($_GPC['page']));
        $psize = 50;
        $show_status = $_GPC['status'];
        $r_type = array( '0' => '退款', '1' => '退货退款', '2' => '换货');
        $condition = " and openid=:openid and ismr=0 and deleted=0 and uniacid=:uniacid and istrade=0 ";
        $params = array(
            ':uniacid' => $uniacid,
            ':openid' => $openid
        );

        //多商户
        $merchdata = $this->merchData();
        extract($merchdata);

        $condition .= " and merchshow=0 ";

        if ($show_status != '') {
            $show_status =intval($show_status);

            switch ($show_status)
            {
                case 0:
                    $condition.=' and status=0 and paytype<>3';
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

        $com_verify = com('verify');

        $s_string = '';

        $list = pdo_fetchall("select id,addressid,ordersn,price,dispatchprice,status,iscomment,isverify,verifyendtime,btype,isbill,createtime,
verified,verifycode,verifytype,iscomment,refundid,expresscom,express,expresssn,finishtime,`virtual`,sendtype,
paytype,expresssn,refundstate,dispatchtype,verifyinfo,merchid,isparent,iscycelbuy,cycelbuy_periodic,userdeleted{$s_string}
 from " . tablename('ewei_shop_order') . " where 1 {$condition} order by createtime desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . " where 1 {$condition}", $params);

        $refunddays = intval($_W['shopset']['trade']['refunddays']);
        if ($is_openmerch == 1) {
            $merch_user = $merch_plugin->getListUser($list,'merch_user');
        }

        foreach ($list as &$row) {

            $param = array();

            if ($row['isparent'] == 1) {
                $scondition = " og.parentorderid=:parentorderid";
                $param[':parentorderid'] = $row['id'];
            } else {
                $scondition = " og.orderid=:orderid";
                $param[':orderid'] = $row['id'];
            }

            //所有商品
            $sql = "SELECT og.id,og.goodsid,og.total,g.title,g.thumb,g.type,g.status,og.oldprice as price,og.title as gtitle,og.optionname as optiontitle,og.optionid,op.specs,g.merchid,og.seckill,og.seckill_taskid,
                og.sendtype,og.expresscom,og.expresssn,og.express,og.sendtime,og.finishtime,og.remarksend,og.single_refundid,og.single_refundstate,og.export_num,og.erp_trade_no
                FROM " . tablename('ewei_shop_order_goods') . " og "
                . " left join " . tablename('ewei_shop_goods') . " g on og.goodsid = g.id "
                . " left join " . tablename('ewei_shop_goods_option') . " op on og.optionid = op.id "
                . " where $scondition order by og.id asc";

            $goods = pdo_fetchall($sql, $param);

            $ismerch = 0;
            $merch_array = array();

            $row['isonlyverifygoods'] = false;
            foreach($goods as &$r){
                $r['seckilltask'] = false;
                if($r['seckill']){
                    $r['seckill_task'] = plugin_run('seckill::getTaskInfo',$r['seckill_taskid']);
                }
                $merchid = (int)$r['merchid'];
                $merch_array[$merchid]= $merchid;
                //读取规格的图片
                if (!empty($r['specs'])) {
                    $thumb = m('goods')->getSpecThumb($r['specs']);
                    if (!empty($thumb)) {
                        $r['thumb'] = $thumb;
                    }
                }
                if($r['type']==5){
                    $row['isonlyverifygoods'] = true;
                }
                if(empty($r['gtitle'])!=true){
                    $r['title']=$r['gtitle'];
                }
            }
            unset($r);

            if (!empty($merch_array)) {
                if (count($merch_array) > 1) {
                    $ismerch = 1;
                }
            }
            $goods = set_medias($goods, 'thumb');

            if(empty($goods)){
                $goods = array();
            }
            foreach($goods as &$r){
                $r['thumb'].="?t=".random(50);
            }
            unset($r);

            $goods_list = array();
            if ($ismerch) {
                $getListUser = $merch_plugin->getListUser($goods);
                $merch_user = $getListUser['merch_user'];

                foreach ($getListUser['merch'] as $k => $v) {
                    if (empty($merch_user[$k]['merchname'])) {
                        $goods_list[$k]['shopname'] = $_W['shopset']['shop']['name'];
                    }else{
                        $goods_list[$k]['shopname'] = $merch_user[$k]['merchname'];
                    }
                    $goods_list[$k]['goods'] = $v;
                }
            } else {
                if ($merchid == 0) {
                    $goods_list[0]['shopname'] = $_W['shopset']['shop']['name'];
                } else {
                    $merch_data = $merch_plugin->getListUserOne($merchid);
                    $goods_list[0]['shopname'] = $merch_data['merchname'];
                }
                $goods_list[0]['goods'] = $goods;
            }


            $row['goods'] = $goods_list;
            $row['goods_num'] = count($goods);
            $cycelbuy_periodic = explode( ',' , $row['cycelbuy_periodic'] );
            $row['phaseNum'] =$cycelbuy_periodic['2'];
            $statuscss = "text-cancel";

            switch ($row['status']) {
                case "-1":
                    $status = "已取消";
                    break;
                case "0":
                    if ($row['paytype'] == 3) {

                        $status = "待发货";
                    } else {
                        $status = "待付款";
                    }
                    $statuscss = "text-cancel";
                    break;
                case "1":
                    if ($row['isverify'] == 1) {
                        $status = "使用中";
                        //lynn核销时间限制判断
                        if($row['verifyendtime'] > 0 && $row['verifyendtime'] < time() ){
                            $row['status'] = -1;
                            $status = "已过期";
                        }
                    } else if (empty($row['addressid'])) {
                        if (!empty($row['ccard'])) {
                            $status = "充值中";
                        } else {
                            $status = "待取货";
                        }
                    } else {
                        $status = "待发货";
                        if($row['sendtype']>0){
                            $status = "部分发货";
                        }
                    }
                    $statuscss = "text-warning";
                    break;
                case "2":
                    $status = "待收货";
                    $statuscss = "text-danger";
                    break;
                case "3":
                    if (empty($row['iscomment'])) {
                        if ($show_status == 5) {
                            $status = "已完成";
                        } else {
                            $status = empty($_W['shopset']['trade']['closecomment']) ? "待评价" : "已完成";

                        }
                    } else {
                        $status = "交易完成";
                    }
                    $statuscss = "text-success";
                    break;
            }
            $row['statusstr'] = $status;
            $row['statuscss'] = $statuscss;
            if ($row['refundstate'] > 0 && !empty($row['refundid'])) {

                $refund = pdo_fetch("select * from " . tablename('ewei_shop_order_refund') . ' where id=:id and uniacid=:uniacid and orderid=:orderid limit 1'
                    , array(':id' => $row['refundid'], ':uniacid' => $uniacid, ':orderid' => $row['id']));

                if (!empty($refund)) {
                    $row['statusstr'] = '待' . $r_type[$refund['rtype']];
                }
            }
            //是否可以退款
            $canrefund = false;
            /*if ($row['status'] == 1 || $row['status'] == 2) {
                $canrefund = true;
                if ($row['status'] == 2 && $row['price'] == $row['dispatchprice']) {
                    if ($row['refundstate'] > 0) {
                        $canrefund = true;
                    } else {
                        $canrefund = false;
                    }
                }
            } else if ($row['status'] == 3) {
                if ($row['isverify'] != 1 && empty($row['virtual'])) { //如果不是核销或虚拟物品，则可以退货
                    if ($row['refundstate'] > 0) {
                        $canrefund = true;
                    } else {
                        if ($refunddays > 0) {
                            $days = intval((time() - $row['finishtime']) / 3600 / 24);
                            if ($days <= $refunddays) {
                                $canrefund = true;
                            }
                        }
                    }
                }
            }*/
            $row['canrefund'] = $canrefund;
            //是否可以核销
            $row['canverify'] = false;

            $canverify = false;

            if ($com_verify) {
                $showverify =  ($row['dispatchtype'] || $row['isverify']) && !$row['isonlyverifygoods'] ;
                if ($row['isverify']) {

                    if(!$row['isonlyverifygoods']) {

                        if ($row['verifytype'] == 0 || $row['verifytype'] == 1 || $row['verifytype'] == 3) {
                            $vs = iunserializer($row['verifyinfo']);
                            $verifyinfo = array(
                                array(
                                    'verifycode' => $row['verifycode'],
                                    'verified' => $row['verifytype'] == 0 ? $row['verified'] : count($vs) >= $row['goods'][0]['goods']['total']
                                )
                            );
                            if ($row['verifytype'] == 0 || $row['verifytype'] == 3) {
                                $canverify = empty($row['verified']) && $showverify;
                            } else if ($row['verifytype'] == 1) {
                                $canverify = count($vs) < $row['goods'][0]['goods']['total'] && $showverify;
                            }

                        } else {

                            $verifyinfo = iunserializer($row['verifyinfo']);

                            $last = 0;
                            foreach ($verifyinfo as $v) {
                                if (!$v['verified']) {
                                    $last++;
                                }
                            }
                            $canverify = $last > 0 && $showverify;
                        }
                    }

                } else if (!empty($row['dispatchtype'])) {
                    $canverify = $row['status'] == 1 && $showverify;
                }
            }

            $row['canverify']  = $canverify;

            if ($is_openmerch == 1) {
                $row['merchname'] = $merch_user[$row['merchid']]['merchname'] ? $merch_user[$row['merchid']]['merchname'] : $_W['shopset']['shop']['name'];
            }

            if($row['isonlyverifygoods']){

                $row['canverify'] = false;
                $verifygood = pdo_fetch("select * from ".tablename('ewei_shop_verifygoods').' where orderid=:orderid limit 1',array(':orderid'=>$row['id']));
                if(!empty($verifygood)){
                    $row['verifygoods_id'] = $verifygood['id'];
                    //剩余次数
                    $verifynum = pdo_fetchcolumn('select sum(verifynum) from ' . tablename('ewei_shop_verifygoods_log') . '    where verifygoodsid =:id  ', array(':id' => $verifygood['id']));
                    //是否过期
                    if(empty($verifygood['limittype']))
                    {
                        $limitdate = intval($verifygood['starttime']) +  intval($verifygood['limitdays'])*86400;

                    }else
                    {
                        $limitdate = intval($verifygood['limitdate']);
                    }
                    $row['canverify']  = $limitdate>=time() ;
                    if( $verifygood['limitnum']>0){
                        $row['canverify'] = $verifynum < $verifygood['limitnum'];
                    }
                }
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

        show_json(1,array('list'=>$list,'pagesize'=>$psize,'total'=>$total));
    }

    function alipay() {
        global $_W, $_GPC;
        $url = urldecode($_GPC['url']);
        if(!is_weixin()){
            header('location: ' . $url);
            exit;
        }
        include $this->template();
    }

    function detail() {

        global $_W, $_GPC;
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($openid, true);
        $orderid = intval($_GPC['id']);
        $ispeerpay = m('order')->checkpeerpay($orderid);//检查是否是代付订单
        // 判断进入订单详情 返回键隐藏
        $isshow = $_GPC['isshow'];
        if (empty($orderid)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }


        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));
        $refundtype = pdo_fetch("select * from " . tablename('ewei_shop_order_refund') . ' where orderid=:orderid limit 1', array(':orderid' => $orderid));

        //如果是周期购订单则跳转周期购商品详情
        if($order['iscycelbuy'] == 1) {
            header("location:" . mobileUrl('cycelbuy/order/detail', array('id' => $order['id'])));
        }

        if (empty($order)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }

        if ($order['merchshow'] == 1) {
            header('location: ' . mobileUrl('order'));
            exit;
        }

        if ($order['userdeleted'] == 2) {
            $this->message('订单已经被删除!','','error');
        }

        if (!empty($order['istrade'])) {
            header('location: ' . mobileUrl('newstore/norder/detail', array('id' => $orderid)));
            exit;
        }

        $isonlyverifygoods = m('order')->checkisonlyverifygoods($order['id']);

//        if ($order['isnewstore'] == 1) {
//            $store_item = m('store')->getStoreInfo($order['storeid']);
//        }


        //如果维过权则取维权信息
        if($order['refundid'] !=0){
            $refund = pdo_fetch("SELECT *  FROM " . tablename('ewei_shop_order_refund') . " WHERE orderid = :orderid and uniacid=:uniacid order by id desc", array(':orderid' => $order['id'], ':uniacid' => $_W['uniacid']));
        }
//        print_r($refund);die();

        $area_set = m('util')->get_area_config_set();
        $new_area = intval($area_set['new_area']);
        $address_street = intval($area_set['address_street']);

        //多商户
        $merchdata = $this->merchData();
        extract($merchdata);

        $merchid = $order['merchid'];
        //商品信息
        $diyform_plugin = p('diyform');
        $diyformfields = "";
        if ($diyform_plugin) {
            $diyformfields = ",og.diyformfields,og.diyformdata";
        }

        $param = array();
        $param[':uniacid'] = $_W['uniacid'];

        if ($order['isparent'] == 1) {
            $scondition = " og.parentorderid=:parentorderid";
            $param[':parentorderid'] = $orderid;
        } else {
            $scondition = " og.orderid=:orderid";
            $param[':orderid'] = $orderid;
        }

        $condition1 = '';
        if(p('ccard')) {
            $condition1 .= ',g.ccardexplain,g.ccardtimeexplain';
        }

        $goodsid_array =array();
        $goods = pdo_fetchall("select og.id,og.single_refundstate,og.sendtime,og.goodsid,og.oldprice as price,og.status1,g.title,og.title as gtitle,g.thumb,g.status, g.cannotrefund, og.total,g.credit,og.optionid,
            og.optionname as optiontitle,g.isverify,g.storeids,og.seckill,g.isfullback,og.export_num,og.erp_trade_no,og.merchid,
            og.seckill_taskid{$diyformfields}{$condition1},og.prohibitrefund  from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where $scondition and og.uniacid=:uniacid ", $param);

		//禁止退款prohibit refund
        $prohibitrefund=false;
        $isfullback=false;
        foreach($goods as &$g){
            if($g['isfullback']){
                $isfullback=true;
                $fullbackgoods = pdo_fetch("SELECT * FROM ".tablename('ewei_shop_fullback_goods')." WHERE goodsid = :goodsid and uniacid = :uniacid  limit 1 ",array(':goodsid'=>$g['goodsid'],':uniacid'=>$uniacid));
                if($g['optionid']){
                    $option = pdo_fetch("select `day`,allfullbackprice,fullbackprice,allfullbackratio,fullbackratio,isfullback
                      from ".tablename('ewei_shop_goods_option')." where id = :id and uniacid = :uniacid ",array(":id"=>$g['optionid'],":uniacid"=>$uniacid));
                    $fullbackgoods['minallfullbackallprice'] = $option['allfullbackprice'];
                    $fullbackgoods['fullbackprice'] = $option['fullbackprice'];
                    $fullbackgoods['minallfullbackallratio'] = $option['allfullbackratio'];
                    $fullbackgoods['fullbackratio'] = $option['fullbackratio'];
                    $fullbackgoods['day'] = $option['day'];
                }
                $g['fullbackgoods'] = $fullbackgoods;
                unset($fullbackgoods,$option);
            }

            $g['seckill_task'] = false;
            if($g['seckill']){
                $g['seckill_task'] = plugin_run('seckill::getTaskInfo',$g['seckill_taskid']);
            }

            if(!empty($g['prohibitrefund']))
            {
                $prohibitrefund=true;
            }
            if(empty($g['gtitle'])!=true){
                $g['title']=$g['gtitle'];
            }

            if($g['merchid'] == 0){
                $g['merchname'] = '集和堂医药';
            }else{
                $g['merchname'] = pdo_getcolumn('ewei_shop_merch_user', array('id' => $g['merchid']), 'merchname');
            }
        }
        unset($g);
        //商品是否支持退换货
        $goodsrefund = true;

        if(!empty($goods)) {
            foreach ($goods as &$g) {
                $goodsid_array[] = $g['goodsid'];
                if (!empty($g['optionid'])) {
                    $thumb = m('goods')->getOptionThumb($g['goodsid'], $g['optionid']);
                    if (!empty($thumb)) {
                        $g['thumb'] = $thumb;
                    }
                }
                if(!empty($g['cannotrefund']) && $order['status']==2){
                    $goodsrefund = false;
                }
            }
            unset($g);
        }
        $diyform_flag = 0;

        if ($diyform_plugin) {
            foreach ($goods as &$g) {
                $g['diyformfields'] = iunserializer($g['diyformfields']);
                $g['diyformdata'] = iunserializer($g['diyformdata']);
                unset($g);
            }

            //订单统一模板
            if (!empty($order['diyformfields']) && !empty($order['diyformdata'])) {
                $order_fields = iunserializer($order['diyformfields']);
                $order_data = iunserializer($order['diyformdata']);
            }
        }

        //收货地址
        $address = false;
        if (!empty($order['addressid'])) {
            $address = iunserializer($order['address']);
            if (!is_array($address)) {
                $address = pdo_fetch('select * from  ' . tablename('ewei_shop_member_address') . ' where id=:id limit 1', array(':id' => $order['addressid']));
            }
        }

        //联系人
        $carrier = @iunserializer($order['carrier']);
        if (!is_array($carrier) || empty($carrier)) {
            $carrier = false;
        }

        //门店
        $store = false;
        if (!empty($order['storeid'])) {

            if ($merchid > 0) {
                $store = pdo_fetch('select * from  ' . tablename('ewei_shop_merch_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));

            } else {
                $store = pdo_fetch('select * from  ' . tablename('ewei_shop_store') . ' where id=:id limit 1', array(':id' => $order['storeid']));
            }
        }

        //核销门店
        $stores = false;
        $showverify = false;  //是否显示消费码
        $canverify = false;  //是否可以核销
        $verifyinfo = false;
        if (com('verify')) {
            $showverify = ( $order['dispatchtype'] || $order['isverify'] ) ;

            if ($order['isverify']) {
                //lynn核销限制时间判断
                if($order['verifyendtime'] > 0 && $order['verifyendtime'] < time()){
                    $order['status'] = -1;
                }
                //核销单
                $storeids = array();
                foreach ($goods as $g) {
                    if (!empty($g['storeids'])) {
                        $storeids = array_merge(explode(',', $g['storeids']), $storeids);
                    }
                }

                if (empty($storeids)) {
                    //全部门店
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
                    }
                } else {
                    if ($merchid > 0) {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                    } else {
                        $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1 and type in(2,3)', array(':uniacid' => $_W['uniacid']));
                    }
                }


//                print_r($stores);exit;


                if ($order['verifytype'] == 0 || $order['verifytype'] == 1 || $order['verifytype'] == 3) {
                    $vs = iunserializer($order['verifyinfo']);
                    $verifyinfo = array(
                        array(
                            'verifycode' => $order['verifycode'],
                            'verified' => $order['verifytype'] == 0 || $order['verifytype'] == 3 ?$order['verified']: count($vs)>=$goods[0]['total']
                        )
                    );
                    if( $order['verifytype']==0 || $order['verifytype'] == 3) {
                        $canverify = empty($order['verified']) && $showverify;
                    } else if( $order['verifytype']==1 ){
                        $canverify = count($vs)<$goods[0]['total']  && $showverify;
                    }

                } else {
                    $verifyinfo = iunserializer($order['verifyinfo']);

                    $last = 0;
                    foreach($verifyinfo as $v){
                        if(!$v['verified']){
                            $last++;
                        }
                    }
                    $canverify = $last>0 && $showverify;
                }
            }
            else if(!empty($order['dispatchtype'])){

                $verifyinfo = array(
                    array(
                        'verifycode' => $order['verifycode'],
                        'verified' => $order['status'] == 3
                    )
                );

                $canverify = $order['status']==1 && $showverify;
            }

        }
        $order['canverify'] = $canverify;
        $order['showverify'] = $showverify;

        //虚拟物品信息
        $order['virtual_str'] = str_replace("\n", "<br/>", $order['virtual_str']);
        $canreturn = false;
        //是否可以退款
        $tradeset = m('common')->getSysset('trade');
        if ($order['status'] == 1 || $order['status'] == 2) {
            $canrefund = true;
            if ($order['status'] == 2 && $order['price'] == $order['dispatchprice']) {
                if ($order['refundstate'] > 0) {
                    $canrefund = true;
                } else {
                    $canrefund = false;
                    //当产品余额抵扣所有金额的时候 允许换货
                    if(!$goodsrefund){
                        //当产品不允许退换货而且抵扣了所有金额之后不显示 退款
                        $canreturn = false;
                    }else{
                        $canreturn = true;
                    }
                }
            }
        } else if ($order['status'] == 3) {
            if ($order['isverify'] != 1 && empty($order['virtual'])) { //如果不是核销或虚拟物品，则可以退货
                if ($order['refundstate'] > 0) {
                    $canrefund = true;
                } else {

                    $refunddays = intval($tradeset['refunddays']);

                    if ($refunddays > 0) {
                        $days = intval((time() - $order['finishtime']) / 3600 / 24);
                        if ($days <= $refunddays) {
                            $canrefund = true;
                        }
                    }
                }
            }

        }

        if (!empty($order['isnewstore']) && $order['status'] > 1) {
            $canrefund = false;
        }
		if($prohibitrefund)
        {
            $canrefund = false;
        }

        if(!$goodsrefund && $canrefund){
            $canrefund = false;
        }


        if(p('ccard')) {

            if(!empty($order['ccard']) && $order['status'] > 1) {
                $canrefund = false;
            }

            $comdata = m('common')->getPluginset('commission');
            if (!empty($comdata['become_goodsid']) && !empty($goodsid_array)) {
                if(in_array($comdata['become_goodsid'], $goodsid_array)) {
                    $canrefund = false;
                }
            }
        }

        $haveverifygoodlog = m('order')->checkhaveverifygoodlog($orderid);
        if($haveverifygoodlog)
        {
            $canrefund = false;
        }

        $order['canrefund'] = $canrefund;

        //如果发货，查找第一条物流
        $express = false;
        $order_goods = array();
        if ($order['status'] >= 2 && empty($order['isvirtual']) && empty($order['isverify'])) {
            $expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);
            if (count($expresslist) > 0) {
                $express = $expresslist[0];
            }
        }
        if($order['sendtype']>0 && $order['status']>=1){
            $order_goods = pdo_fetchall("select orderid,goodsid,sendtype,expresscom,expresssn,express,sendtime from ".tablename('ewei_shop_order_goods')."
            where orderid = ".$orderid." and uniacid = ".$uniacid." and sendtype > 0 group by sendtype order by sendtime asc ");
            $expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);
            if (count($expresslist) > 0) {
                $express = $expresslist[0];
            }
            $order['sendtime'] = $order_goods[0]['sendtime'];
        }
        $shopname = $_W['shopset']['shop']['name'];

        //显示核销码
        if( $order['canverify']&&$order['status']!=-1&&$order['status']!=0)
        {
            $query = array('id' => $order['id'], 'verifycode' => $order['verifycode']);

            if( !$isonlyverifygoods) {
                if (empty($order['istrade'])) {
                    $url = mobileUrl('verify/detail', $query, true);
                } else {
                    $url = mobileUrl('verify/tradedetail', $query, true);
                }
                $verifycode = $order['verifycode'];
                $qrcodeimg = m('qrcode')->createQrcode($url);

                if(strlen($verifycode)==8)
                {
                    $verifycode= substr($verifycode,0,4)." ".substr($verifycode,4,4);
                }
                else if(strlen($verifycode)==9)
                {
                    $verifycode= substr($verifycode,0,3)." ".substr($verifycode,3,3)." ".substr($verifycode,6,3);
                }
            }


        }



        if (!empty($order['merchid']) && $is_openmerch == 1)
        {
            $merch_user = $merch_plugin->getListUser($order['merchid']);
            $shopname = $merch_user['merchname'];
            $shoplogo = tomedia($merch_user['logo']);
        }

        if(com('coupon')){
            $activity = com('coupon') -> activity($order['price']);
        }
        
        // 虚拟卡密
        if(!empty($order['virtual']) && !empty($order['virtual_str'])){
            $ordervirtual = m('order')->getOrderVirtual($order);
            $virtualtemp = pdo_fetch('SELECT linktext, linkurl,description FROM '. tablename('ewei_shop_virtual_type'). ' WHERE id=:id AND uniacid=:uniacid LIMIT 1', array(':id'=>$order['virtual'], ':uniacid'=>$_W['uniacid']));
        }

        //秒杀风格色
        if($order['seckilldiscountprice']>0&& p('diypage')){
            $diypagedata = m('common')->getPluginset('diypage');
            $diypage = p('diypage')->seckillPage($diypagedata['seckill']);
            if(!empty($diypage)){
                $seckill_color=$diypage['seckill_color'];
            }
        }

        //判断是否使用会员卡
        $use_membercard = false;
        $membercard_info = array();
        $plugin_membercard = p('membercard');
        if($plugin_membercard){
            $ifuse = $plugin_membercard->if_order_use_membercard($orderid);
            if($ifuse){
                $use_membercard = true;
                $card_text = $ifuse['name'].'优惠';
                $card_dec_price = $ifuse['dec_price'];
            }
        }

        // 虚拟商品自动发货不能退款
        if ($order['isvirtualsend'] && $order['isvirtual']) {
            $order['canrefund'] = false;
        }

        //是否显示单品退换 (不是套餐 && 不是代付 && 不是全返 && 开启了单品退换开关) || 订单维权状态==单品维权
        if((empty($order['ispackage']) && empty($ispeerpay) && empty($isfullback) && !empty($tradeset['single_refund'])) || $order['refundstate']==3){
            $is_single_refund=true;
        }else{
            $is_single_refund=false;
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

    function express() {
        global $_W, $_GPC;

        global $_W, $_GPC;
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $orderid = intval($_GPC['id']);
        $sendtype = intval($_GPC['sendtype']);
        $bundle = trim($_GPC['bundle']);


        if (empty($orderid)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }
        $order = pdo_fetch("select * from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));
        if (empty($order)) {
            header('location: ' . mobileUrl('order'));
            exit;
        }
        $bundlelist = array();
        if($order['sendtype']>0 && $sendtype == 0){
            for($i=1;$i<=intval($order['sendtype']);$i++){
                $bundlelist[$i]['sendtype'] = $i;
                $bundlelist[$i]['orderid'] = $orderid;
                $bundlelist[$i]['goods'] = pdo_fetchall("select g.title,g.thumb,og.total,og.optionname as optiontitle,og.expresssn,og.express,
                    og.sendtype,og.expresscom,og.sendtime from " . tablename('ewei_shop_order_goods') . " og "
                    . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
                    . " where og.orderid=:orderid and og.sendtype = ".$i." and og.uniacid=:uniacid ", array(':uniacid' => $uniacid, ':orderid' => $orderid));
                if(empty($bundlelist[$i]['goods'])){
                    unset($bundlelist[$i]);
                }
            }
            $bundlelist = array_values($bundlelist);
        }
        if (empty($order['addressid'])) {
            $this->message('订单非快递单，无法查看物流信息!');
        }
        if (!($order['status'] >= 2) && !($order['status']>=1 && $order['sendtype']>0)) {
            $this->message('订单未发货，无法查看物流信息!');
        }
        $condition = '';
        //商品信息
        if($sendtype>0){
            $condition = ' and og.sendtype = '.$sendtype;
        }
        $goods = pdo_fetchall("select og.goodsid,og.price,g.title,g.thumb,og.total,g.credit,og.optionid,og.optionname as optiontitle,g.isverify,og.expresssn,og.express,
            og.sendtype,og.expresscom,og.sendtime,g.storeids{$diyformfields}
            from " . tablename('ewei_shop_order_goods') . " og "
            . " left join " . tablename('ewei_shop_goods') . " g on g.id=og.goodsid "
            . " where og.orderid=:orderid and og.single_refundtime=0 ".$condition." and og.uniacid=:uniacid ", array(':uniacid' => $uniacid, ':orderid' => $orderid));
        if($sendtype>0){
            $order['express'] = $goods[0]['express'];
            $order['expresssn'] = $goods[0]['expresssn'];
            $order['expresscom'] = $goods[0]['expresscom'];
        }
        $expresslist = m('util')->getExpressList($order['express'], $order['expresssn']);

        include $this->template();
    }

    function dispatch() {
        global $_W, $_GPC;

        $merchid = intval($_GPC['merchid']);

        $list = m('dispatch')->getDispatchList($merchid);

        include $this->template();

    }

    //重新下单  2019-8-1
    public function again()
    {
        try {
            global $_W;
            global $_GPC;
            $_W["openid"] = $_SESSION['wechat_openid'];
            $orderid = intval($_GPC["orderid"]);
            $order = pdo_fetch('select id,status,paytype from ' . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1', array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W["openid"]));
            if($order['status']!=-1 && $order['paytyoe']!=0){
                show_json(0, '订单无法重新下单~~');
                exit();
            }
            //查询该订单商品
            $goods = pdo_fetchall('select og.id,og.goodsid,og.total,g.marketprice from ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_goods') . ' g on g.id=og.goodsid where og.uniacid='.$_W['uniacid'].' and orderid='.$orderid);
            if(!empty($goods)){
                //重新添加购物车
                foreach($goods as $k=>$v){
                    $carts = [
                        'uniacid'=>$_W['uniacid'],
                        'openid'=>$_W["openid"],
                        'goodsid'=>$v['goodsid'],
                        'total'=>$v['total'],
                        'marketprice'=>$v['marketprice'],
                        'deleted'=>0,
                        'createtime'=>time(),
                    ];
                    pdo_insert('ewei_shop_member_cart', $carts);
                }
                show_json(1);
            }
            show_json(0,'查询不到该订单商品');
        } catch (Exception $e) {
            show_json(0, '网络错误~~');
        }
    }





}
