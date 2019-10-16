<?php

if (!defined('IN_IA')) {
	exit('Access Denied');
}

class Index_EweiShopV2Page extends MobileLoginPage {

	function main() {
		global $_W, $_GPC;
        $activity = isset($_GPC['activity']) ? $_GPC['activity'] : 0;
		$allcategory = m('shop')->getCategory();
		$catlevel = intval($_W['shopset']['category']['level']);
		$opencategory = true; //是否自己商品不同步分类
		$plugin_commission = p('commission');
		if ($plugin_commission && intval($_W['shopset']['commission']['level']) > 0) {
			$mid = intval($_GPC['mid']);
			if (!empty($mid) && empty($_W['shopset']['commission']['closemyshop']) && !empty($_W['shopset']['commission']['select_goods'])) {
				$shop = p('commission')->getShop($mid);
				if (empty($shop['selectcategory']) && !empty($shop['selectgoods'])) {
					$opencategory = false;
				}
			}
		}

		include $this->template();
	}
	function gift(){
		global $_W,$_GPC;
		$uniacid = $_W['uniacid'];
		$giftid = intval($_GPC['id']);

		$gift = pdo_fetch("select * from ".tablename('ewei_shop_gift')." where uniacid = ".$uniacid." and id = ".$giftid." and starttime <= ".time()." and endtime >= ".time()." and status = 1 ");
		$giftgoodsid = explode(",",$gift['giftgoodsid']);
		$giftgoods = array();
		if(!empty($giftgoodsid)){
			foreach($giftgoodsid as $key => $value){
				$giftgoods[$key] = pdo_fetch("select id,status,title,thumb,marketprice,total from ".tablename('ewei_shop_goods')." where uniacid = ".$uniacid." and deleted = 0  and id = ".$value." and status = 2 ");
			}
			$giftgoods = array_filter($giftgoods);
		}

		include $this->template();
	}

	function get_list() {


		global $_GPC, $_W;

		$args = array(
			'pagesize' => 10,
			'page' => intval($_GPC['page']),
			'isnew' => trim($_GPC['isnew']),
			'ishot' => trim($_GPC['ishot']),
			'isrecommand' => trim($_GPC['isrecommand']),
			'isdiscount' => trim($_GPC['isdiscount']),
			'istime' => trim($_GPC['istime']),
			'issendfree' => trim($_GPC['issendfree']),
			'keywords' => trim($_GPC['keywords']),
			'cate' => trim($_GPC['cate']),
			'order' => $_GPC['order']?trim($_GPC['order']):'displayorder desc,total desc,createtime desc',
			'by' => trim($_GPC['by']),
			'activity' => isset($_GPC['activity']) ? intval($_GPC['activity']) : 0,
		);
		
		//判断是否开启自选商品
		$plugin_commission = p('commission');
		if ($plugin_commission && intval($_W['shopset']['commission']['level'])>0 && empty($_W['shopset']['commission']['closemyshop']) && !empty($_W['shopset']['commission']['select_goods'])) {
            $frommyshop = intval($_GPC['frommyshop']);
			$mid = intval($_GPC['mid']);
			if (!empty($mid) && !empty($frommyshop)) {
				$shop = p('commission')->getShop($mid);
				if (!empty($shop['selectgoods'])) {
					$args['ids'] = $shop['goodsids'];
				}
			}
		}
		$this->_condition($args);
	}

	function query() {
		global $_GPC, $_W;
		$args = array(
			'pagesize' => 10,
			'page' => intval($_GPC['page']),
			'isnew' => trim($_GPC['isnew']),
			'ishot' => trim($_GPC['ishot']),
			'isrecommand' => trim($_GPC['isrecommand']),
			'isdiscount' => trim($_GPC['isdiscount']),
			'istime' => trim($_GPC['istime']),
			'keywords' => trim($_GPC['keywords']),
			'cate' => trim($_GPC['cate']),
			'order' => trim($_GPC['order']),
			'by' => trim($_GPC['by']),
            'activity' => isset($_GPC['activity']) ? intval($_GPC['activity']) : 0,
		);
		$this->_condition($args);
	}

	private function _condition($args)
	{
		global $_W;
		global $_GPC;
		$merch_plugin = p('merch');
		$merch_data = m('common')->getPluginset('merch');
		if ($merch_plugin && $merch_data['is_openmerch']) {
			$args['merchid'] = intval($_GPC['merchid']);
		}

		if (isset($_GPC['nocommission'])) {
			$args['nocommission'] = intval($_GPC['nocommission']);
		}

		$goods = m('goods')->getList($args);

        //会员等级
        $user_level = pdo_getcolumn('ewei_shop_member', array('openid' => $_W['openid']), 'level');
        if($user_level > 0){
            $gl = 'level'.$user_level.'_pay';
            foreach($goods['list'] as $k=>$v){
                $dis = json_decode($v['discounts'],true);
                //会员等级对应的价格
                if(array_key_exists($gl, $dis) and !empty($dis[$gl])){
                    $goods['list'][$k]['minprice'] = $dis[$gl];
                }
                unset($v['discounts']);
            }
        }

        $openid = $_W['openid'];
        $menber_goods = pdo_fetchall('select goodsid,total from ' . tablename('ewei_shop_member_cart'). ' where openid=:openid and uniacid=:uniacid and deleted=:deleted', array(':uniacid' => $_W['uniacid'], ':openid' => $openid, ':deleted' => 0));
        foreach($goods['list'] as $key=>$g){
        foreach($menber_goods as $v){
        	if($g['id'] == $v['goodsid']){
                $goods['list'][$key]['member1'] = 1;
                $goods['list'][$key]['total1'] = $v['total'];
        	}else{
        		$goods['list'][$key]['member1'] = 0;
        	}
        }
        }

		show_json(1, array('list' => $goods['list'], 'total' => $goods['total'], 'pagesize' => $args['pagesize']));
	}

}
