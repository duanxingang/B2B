<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
require EWEI_SHOPV2_PLUGIN . 'app/core/page_mobile.php';

class Order_EweiShopV2Page extends AppMobilePage
{

    //买卡生成订单
    public function create_order(){
        global $_W, $_GPC;
        $id = $_GPC['id'];
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        if(empty($openid) || empty($id)){
            app_error( AppError::$ParamsError );
        }
        $plugin_membercard = p('membercard');
        if(!$plugin_membercard){
            app_error(AppError::$PluginNotFound);
        }

        $card = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_card') . " 
				WHERE id =:id and uniacid=:uniacid and isdelete=0 limit 1", array(':uniacid' => $_W['uniacid'], ':id' => $id));
        if(empty($card)){
            app_error(AppError::$CardNotFund);
        }
        if(empty($card['status'])){
            app_error(AppError::$CardisStop);
        }
        if($card['stock'] <= 0){
            app_error(82033,'会员卡库存不足');
        }

        //检测是否已经买过
        $has_flag = $plugin_membercard->check_Hasget($id,$openid);
        if($has_flag['errno'] == 0 && $has_flag['using'] == 2){
            app_error( 82033, '你已购买过此卡并且永久有效无需重复购买');
        }

        $orderno = m('common')->createNO('member_card_order', 'orderno', 'MC');
        $data = array(
            'uniacid'   => $uniacid,
            'openid'   => $openid,
            'member_card_id'   => $id,
            'createtime'   => time(),
            'total'   => $card['price'],
            'status'   => 0,
            'orderno'   => $orderno
        );

        $condition = " and uniacid=:uniacid and openid=:openid and member_card_id=:cardid";
        $params = array(':uniacid' => $_W['uniacid'],':openid'=>$openid,':cardid'=>$id);
        //查询未支付的记录
        $wait_pay = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_card_order') . " 
				WHERE status =0 {$condition} limit 1", $params);

        if($wait_pay){
            $result = pdo_update('ewei_shop_member_card_order', $data, array('id' => $wait_pay['id'], 'uniacid' => $_W['uniacid']));
            $order_id = $wait_pay['id'];
        }else{
            $result = pdo_insert('ewei_shop_member_card_order', $data);
            $order_id = pdo_insertid();
        }
        if(!$result){
            app_error( 82034, '生成订单失败请稍后重试');
        }
        $data['order_id'] = $order_id;
        app_json(array('order'=>$data));
    }


    //进入支付页面支付订单
    public function pay($params=array(),$mine=array()){
        global $_W, $_GPC;
        $order_id = $_GPC['order_id'];
        $openid = $_W['openid'];
        $uniacid = $_W['uniacid'];
        $member = m('member')->getMember($openid, true);
        if(empty($openid) || empty($order_id)){
            app_error( AppError::$ParamsError );
        }
        $plugin_membercard = p('membercard');
        if(!$plugin_membercard){
            app_error(AppError::$PluginNotFound);
        }

        //查询订单
        $condition = " and uniacid=:uniacid and openid=:openid and id=:order_id";
        $params = array(':uniacid' => $_W['uniacid'],':openid'=>$openid,':order_id'=>$order_id);
        $order = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_card_order') . " 
				WHERE 1 {$condition} limit 1", $params);
        if(empty($order)){
            app_error(AppError::$OrderNotFound);
        }
        if($order['status'] > 0 && $order['paytime']){
            app_error(AppError::$OrderAlreadyPay);
        }

        //查询会员卡
        $card = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_member_card') . " 
				WHERE id =:id and uniacid=:uniacid limit 1", array(':uniacid' => $_W['uniacid'], ':id' => $order['member_card_id']));
        if(empty($card)){
            app_error(AppError::$CardNotFund);
        }
        if(empty($card['status'])){
            app_error(AppError::$CardisStop);
        }
        if($card['isdelete']){
            app_error(AppError::$CardisDel);
        }
        if($card['stock'] <= 0){
            app_error(82033,'会员卡库存不足');
        }

        //系统支付日志
        $log = pdo_fetch('SELECT * FROM ' . tablename('core_paylog') . '
		 WHERE `uniacid`=:uniacid AND `module`=:module AND `tid`=:tid limit 1', array(':uniacid' => $uniacid, ':module' => 'ewei_shopv2', ':tid' => $order['orderno']));
        if (!empty($log) && $log['status'] != '0') {
            app_error(AppError::$OrderAlreadyPay);
        }
        if (!empty($log) && $log['status'] == '0') {
            pdo_delete('core_paylog', array('plid' => $log['plid']));
            $log = null;
        }

        if (empty($log)) {
            $log = array(
                'uniacid' => $uniacid,
                'openid' => $member['uid'],
                'module' => "ewei_shopv2",
                'tid' => $order['orderno'],
                'fee' => $order['total'],
                'status' => 0,
            );
            pdo_insert('core_paylog', $log);
            $plid = pdo_insertid();
        }

        //支付配置
        $set = m('common')->getSysset(array('shop', 'pay'));
        //余额支付
        $credit = array('success' => false);
        if (isset($set['pay']) && $set['pay']['credit'] == 1) {
            $credit = array(
                'success' => true,
                'current' => $member['credit2']
            );
        }
        // 微信支付
        $wechat = array('success' => false);
        if (!empty($set['pay']['wxapp']) && $order['total']>0 && $this->iswxapp){
            $payinfo = array(
                'openid' => $_W['openid_wa'],
                'title' => $set['shop']['name'] . "激活会员卡",
                'tid' => $order['orderno'],
                'fee' => $order['total']
            );
            $res = $this->model->wxpay($payinfo,20);
            if (!is_error($res)){
                $wechat = array(
                    'success' => true,
                    'payinfo'=>$res
                );
                if(!empty($res['package']) && strexists($res['package'], 'prepay_id=')){
                    $prepay_id = str_replace('prepay_id=', '', $res['package']);
                    pdo_update('ewei_shop_member_card_order', array('wxapp_prepay_id'=>$prepay_id), array('id'=>$order_id, 'uniacid'=>$_W['uniacid']));
                }
            }else{
                $wechat['payinfo']=$res;
            }
        }


        app_json(array(
            'order' => array(
                'id' => $order['id'],
                'orderno' => $order['orderno'],
                'total' => $order['total'],
                'title' => $set['shop']['name'] . "激活会员卡",
            ),
            'credit' => $credit,
            'wechat' => $wechat
        ));
    }

    //支付完成
    public function complete(){
        global $_W, $_GPC;
        $orderid = intval($_GPC['id']);             //订单ID
        $uniacid = $_W['uniacid'];
        $openid = $_W['openid'];

        $member = m('member')->getMember( $openid );

        //订单查询
        if(empty($orderid)){
            app_error( AppError::$ParamsError );
        }
        $order = pdo_fetch("select * from " . tablename('ewei_shop_member_card_order') . ' where id = :orderid and uniacid=:uniacid and openid=:openid'
            , array(':orderid' => $orderid, ':uniacid' => $uniacid, ':openid' => $openid));

        if(empty($order)){
            app_error( AppError::$ParamsError );
        }

        //会员卡查询
        $plugin_membercard = p('membercard');
        if(!$plugin_membercard){
            app_error(AppError::$PluginNotFound);
        }
        $card = $plugin_membercard->getMemberCard($order['member_card_id']);
        if(empty($card)){
            app_error(AppError::$CardNotFund);
        }

        //支付方式
        $type = $_GPC['type'];
        if (!in_array($type, array('wechat', 'credit'))) {
            app_error( 1,'未找到支付方式！' );
        }

       //付款日志
        $orderno = $order['orderno'];
        $params = array(':tid'=>$orderno,':module'=>'ewei_shopv2',':uniacid'=>$uniacid);
        $log = pdo_fetch("SELECT * FROM " . tablename('core_paylog') . " WHERE `module`=:module AND `tid`=:tid AND `uniacid`=:uniacid limit 1", $params);
        if (empty($log)) {
            app_error( 1,'支付出错,请重试' );
        }

        //余额支付
        if ($type == 'credit') {
            $credits = m('member')->getCredit($openid, 'credit2');
            $fee = floatval($log['fee']);
            if ($credits < $fee || $credits < 0) {
                app_error( 1,'余额不足，请充值！' );
            }

            //余额
            $result = m('member')->setCredit($openid, 'credit2', -$fee, array($member['uid'], '购买会员卡'.$card['name'].'扣除余额' . $fee));

            if (is_error($result)) {
                app_error(82035, $result['message'] );
            }
            $plugin_membercard->payResult($log['tid'],$type);

            //pdo_update('ewei_shop_member_card_order',$record, array('id' => $orderid));

            app_json(array('orderno' => $log,'fee' => $fee , 'type' => '余额支付','msg'=>'支付成功'));

        } else if ($type == 'wechat') {
            /*$payquery = m('finance')->isWeixinPay($orderno,$log['fee']);
            $payqueryBorrow = m('finance')->isWeixinPayBorrow($orderno,$log['fee']);*/
            $payquery = $this->model->isWeixinPay($orderno, $order['total']);
            if (!is_error($payquery)) {
                //微信支付
                $plugin_membercard->payResult($log['tid'], $type);
                //pdo_update('ewei_shop_member_card_order', $record, array('id' => $orderid));

                app_json(array('orderno' => $log,'fee' => $log['fee'] , 'type' => '微信支付','msg'=>'支付成功'));
            }else{
                app_error( 1,'支付出错，请重试（1）' );
            }
        }
    }

}