<?php  if( !defined("IN_IA") ) 
{
	exit( "Access Denied" );
}
class Shi_EweiShopV2Page extends MobileLoginPage 
{
	public function main() 
	{
		global $_W;
		global $_GPC;
		$uniacid = $_W["uniacid"];
		$sec = pdo_fetch("select * from " . tablename('ewei_shop_payment') . ' where uniacid=:uniacid  limit 1', array(':uniacid' => $uniacid));
		$options["appid"] = trim($sec["sub_appid"]);
		$options["mch_id"] = trim($sec["sub_mch_id"]);
		$options["apikey"] = trim($sec["apikey"]);
		$tid1 = $_GPC['ordersn'];
		if(empty($tid1)){
			header("location: " . mobileUrl('order'));
			exit();
		}
		$price = '';
		$h5pay = m('common')->wechat_order_query($tid1,$price,$options);
		$ordersn = explode('-',$tid1);
		$ordersn = $ordersn[0];
		$order = pdo_fetch("select id,status from " . tablename("ewei_shop_order") . " where ordersn=:ordersn and uniacid=:uniacid limit 1", array( ":ordersn" => $ordersn, ":uniacid" => $uniacid));
		if($order["status"] >= 1){
			header("location: " . mobileUrl('order'));
			exit();
		}else{
			if($h5pay['return_code'] == "SUCCESS" && $h5pay['result_code'] == "SUCCESS"){
           $upd = pdo_update("ewei_shop_order", array( "paytype" => 21, "status" => 1, "transid" => $h5pay['transaction_id']), array( "ordersn" => $ordersn, "uniacid" => $uniacid ));
               header("location: " . mobileUrl('order/detail',array('id'=>$order['id'])));
               exit();
          }
		}
		header("location: " . mobileUrl('order/pay',array('id'=>$order['id'])));
		
		
	}

}
?>