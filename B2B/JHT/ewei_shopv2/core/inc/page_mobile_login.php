<?php
if (!defined('IN_IA')) {
	exit('Access Denied');
}

class MobileLoginPage extends MobilePage
{
	public function __construct()
	{
		global $_W;
		global $_GPC;
		parent::__construct();
		$this->is_account_land();
		$_W['openid'] = $_SESSION['wechat_openid'];
	    //$this->is_company_reg();
	}
}

?>
