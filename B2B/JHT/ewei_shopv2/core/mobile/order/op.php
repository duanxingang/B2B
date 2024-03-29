<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Op_EweiShopV2Page extends MobileLoginPage {

    /**
     * 取消订单
     * @global type $_W
     * @global type $_GPC
     */
    function cancel() {

        global $_W, $_GPC;
        $orderid = intval($_GPC['id']);
        $order = pdo_fetch("select id,ordersn,openid,status,deductcredit,deductcredit2,deductprice,couponid,isparent,`virtual`,`virtual_info`,merchid from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));
        if (empty($order)) {
            show_json(0, '订单未找到');
        }
        if($order['status'] > 0){
            show_json(0, '订单已支付，不能取消!');
        }
        if($order['status'] < 0){
            show_json(0, '订单已经取消!');
        }

        if (!empty($order['virtual']) && $order['virtual'] != 0) {

            $goodsid = pdo_fetch('SELECT goodsid FROM '.tablename('ewei_shop_order_goods').' WHERE uniacid = '.$_W['uniacid'].' AND orderid = '.$order['id']);

            $typeid = $order['virtual'];
            $vkdata = ltrim($order['virtual_info'],'[');
            $vkdata = rtrim($vkdata,']');
            $arr = explode('}',$vkdata);
            foreach($arr as $k => $v){
                if(!$v){
                    unset($arr[$k]);
                }
            }
            $vkeynum = count($arr);

            //未付款卡密变为未使用
            pdo_query("update " . tablename('ewei_shop_virtual_data') . ' set openid="",usetime=0,orderid=0,ordersn="",price=0,merchid='.$order['merchid'].' where typeid=' . intval($typeid).' and orderid = '.$order["id"]);

            //模板减少使用数据
            pdo_query("update " . tablename('ewei_shop_virtual_type') . " set usedata=usedata-".$vkeynum." where id=" .intval($typeid));

        }

        //处理订单库存及用户积分情况(赠送积分)
        m('order')->setStocksAndCredits($orderid, 2);


        //返还抵扣积分
        if ($order['deductprice'] > 0) {
            m('member')->setCredit($order['openid'], 'credit1', $order['deductcredit'], array('0', $_W['shopset']['shop']['name'] . "购物返还抵扣积分 积分: {$order['deductcredit']} 抵扣金额: {$order['deductprice']} 订单号: {$order['ordersn']}"));
        }

        //返还抵扣余额
        m('order')->setDeductCredit2($order);

        //退还优惠券 退还之前先检测
        if (com('coupon') && !empty($order['couponid'])) {

            //检测当前优惠券有没有使用过
            $plugincoupon = com('coupon');
            if ($plugincoupon) {
                $coupondata=  $plugincoupon->getCouponByDataID($order['couponid']);
                if($coupondata['used']!=1){
                    com('coupon')->returnConsumeCoupon($orderid); //手机关闭订单
                }
            }

        }

        pdo_update('ewei_shop_order', array('status' => -1, 'canceltime' => time(), 'closereason' => trim($_GPC['remark'])), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));


        if (!empty($order['isparent'])) {
            pdo_update('ewei_shop_order', array('status' => -1, 'canceltime' => time(), 'closereason' => trim($_GPC['remark'])), array('parentid' => $order['id'], 'uniacid' => $_W['uniacid']));
        }

        //模板消息
        m('notice')->sendOrderMessage($orderid);

        show_json(1);
    }

    /**
     * 确认收货
     * @global type $_W
     * @global type $_GPC
     */
    function finish() {

        global $_W, $_GPC;
        $orderid = intval($_GPC['id']);

        //单品退换货，确认收货后取消维权
        pdo_update('ewei_shop_order_goods', array('single_refundstate' => 0), array('orderid' => $orderid, 'uniacid' => $_W['uniacid']));

        $order = pdo_fetch("select id,status,openid,couponid,price,refundstate,refundid,ordersn,price from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));
        if (empty($order)) {
            show_json(0, '订单未找到');
        }
        if ($order['status'] != 2) {
            show_json(0, '订单不能确认收货');
        }
        if ($order['refundstate'] > 0 && !empty($order['refundid'])) {
            $change_refund = array();
            $change_refund['status'] = -2;
            $change_refund['refundtime'] = time();
            pdo_update('ewei_shop_order_refund', $change_refund, array('id' => $order['refundid'], 'uniacid' => $_W['uniacid']));
        }

        pdo_update('ewei_shop_order', array('status' => 3, 'finishtime' => time(), 'refundstate' => 0), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));

//        //处理积分
        m('order')->setStocksAndCredits($orderid, 3);

        //商品全返
        m('order')->fullback($orderid);
        //show_json(0, $res);

        //会员升级
        m('member')->upgradeLevel($order['openid'], $orderid);

        //余额赠送
        m('order')->setGiveBalance($orderid, 1);

        //发送赠送优惠券
        if (com('coupon')) {
            $refurnid = com('coupon')->sendcouponsbytask($orderid); //订单支付
        }

        //优惠券返利
        if (com('coupon') && !empty($order['couponid'])) {
            com('coupon')->backConsumeCoupon($orderid); //手机收货
        }

        //模板消息
        m('notice')->sendOrderMessage($orderid);

        //打印机打印
        com_run('printer::sendOrderMessage',$orderid);

        //排队全返
        if (p('lineup')) {
            p('lineup')->checkOrder($order);
        }

        //分销检测
        if (p('commission')) {
            p('commission')->checkOrderFinish($orderid);
        }
        //抽奖
        if(p('lottery')){
            //type 1:消费 2:签到 3:任务 4:其他
            $res = p('lottery')->getLottery($_W['openid'],1,array('money'=>$order['price'],'paytype'=>2));
            if($res){
                p('lottery')->getLotteryList($_W['openid'],array('lottery_id'=>$res));
            }
        }
        // 任务中心
        if (p('task')){
            p('task')->checkTaskProgress($order['price'],'order_full','',$order['openid']);
        }


        show_json(1,array('url'=>mobileUrl('order',array('status'=>3))));
    }

    /**
     * 删除或恢复订单
     * @global type $_W
     * @global type $_GPC
     */
    function delete() {
        global $_W, $_GPC;

        //删除订单
        $orderid = intval($_GPC['id']);
        $userdeleted = intval($_GPC['userdeleted']);

        $order = pdo_fetch("select id,status,refundstate,refundid from " . tablename('ewei_shop_order') . ' where id=:id and uniacid=:uniacid and openid=:openid limit 1'
            , array(':id' => $orderid, ':uniacid' => $_W['uniacid'], ':openid' => $_W['openid']));
        if (empty($order)) {
            show_json(0, '订单未找到!');
        }

        if ($userdeleted == 0) {
            if ($order['status'] != 3) {
                show_json(0, '无法恢复');
            }
        } else {
            if ($order['status'] != 3 && $order['status'] != -1) {
                show_json(0, '无法删除');
            }

            if ($order['refundstate'] > 0 && !empty($order['refundid'])) {

                $change_refund = array();
                $change_refund['status'] = -2;
                $change_refund['refundtime'] = time();
                pdo_update('ewei_shop_order_refund', $change_refund, array('id' => $order['refundid'], 'uniacid' => $_W['uniacid']));
            }
        }

        pdo_update('ewei_shop_order', array('userdeleted' => $userdeleted, 'refundstate' => 0), array('id' => $order['id'], 'uniacid' => $_W['uniacid']));
        show_json(1);
    }

}
