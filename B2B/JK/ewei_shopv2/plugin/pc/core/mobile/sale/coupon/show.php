<?php
if (!defined('IN_IA')) {
	exit('Access Denied');
}
require EWEI_SHOPV2_PLUGIN . "pc/core/page_login_mobile.php";
class Show_EweiShopV2Page extends PcMobileLoginPage
{
	public function main()
	{
		include $this->template('pc/sale/coupon/my/showcoupons');
	}

	public function main2()
	{
		include $this->template('pc/sale/coupon/my/showcoupons2');
	}
}

?>
