<?php

if (!defined('IN_IA')) {
    exit('Access Denied');
}
require EWEI_SHOPV2_PLUGIN . 'app/core/page_mobile.php';

class Goods_EweiShopV2Page extends AppMobilePage
{

    /**
     * 商品列表
     */
    public function get_list() {
        global $_GPC, $_W;

        $couponid = intval($_GPC['couponid']);
        if(!empty($couponid)){
            $this->getlistbyCoupon($couponid);
            return;
        }

        $args = array(
            'pagesize' => intval($_GPC['pagesize']),
            'page' => intval($_GPC['page']),
            'isnew' => trim($_GPC['isnew']),
            'ishot' => trim($_GPC['ishot']),
            'isrecommand' => trim($_GPC['isrecommand']),
            'isdiscount' => trim($_GPC['isdiscount']),
            'istime' => trim($_GPC['istime']),
            'keywords' => trim($_GPC['keywords']),
            'cate' => intval($_GPC['cate']),
            'order' => trim($_GPC['order']),
            'by' => trim($_GPC['by']),
            'from' => 'miniprogram', //传入数据来源，为了不显示批发商品，支持后可删除
        );

        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $args['merchid'] = intval($_GPC['merchid']);
        }

        if (isset($_GPC['nocommission'])) {
            $args['nocommission'] = intval($_GPC['nocommission']);
        }

        $goods = m('goods')->getList($args);

        $saleout = !empty($_W['shopset']['shop']['saleout'])?tomedia($_W['shopset']['shop']['saleout']):'/static/images/saleout-2.png';

        $goods_list = array();
        if($goods['total']>0){
            $goods_list = $goods['list'];
            foreach ($goods_list as $index=>$item){
                if($goods_list[$index]['isdiscount']){
                    if( $goods_list[$index]['isdiscount_time']>time()){
                        $goods_list[$index]['isdiscount'] = 0;
                    }
                }
                $goods_list[$index]['minprice'] = (float)$goods_list[$index]['minprice'];
                unset($goods_list[$index]['marketprice'], $goods_list[$index]['maxprice'], $goods_list[$index]['isdiscount_discounts'], $goods_list[$index]['description'], $goods_list[$index]['discount_time']);

                if($item['total']<1){
                    $goods_list[$index]['saleout'] = $saleout;
                }
            }
        }

        app_json(array('list' => $goods_list, 'total' => $goods['total'], 'pagesize' => $args['pagesize']));
    }

    /**
     * 获取商品列表
     */
    public function getlistbyCoupon() {
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
            'order' => trim($_GPC['order']),
            'by' => trim($_GPC['by']),
            'couponid'=>trim($_GPC['couponid']),
            'merchid'=>intval($_GPC['merchid'])
        );
        //判断是否开启自选商品
        $plugin_commission = p('commission');
        if ($plugin_commission && intval($_W['shopset']['commission']['level'])>0 && empty($_W['shopset']['commission']['closemyshop']) && !empty($_W['shopset']['commission']['select_goods'])) {
            $mid = intval($_GPC['mid']);
            if (!empty($mid)) {
                $shop = p('commission')->getShop($mid);
                if (!empty($shop['selectgoods'])) {
                    $args['ids'] = $shop['goodsids'];
                }
            }
        }

        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $args['merchid'] = intval($_GPC['merchid']);
        }

        if (isset($_GPC['nocommission'])) {
            $args['nocommission'] = intval($_GPC['nocommission']);
        }
        $goods = m('goods')->getListbyCoupon($args);

        app_json(array('list' => $goods['list'], 'total' => $goods['total'], 'pagesize' => $args['pagesize']));
    }

    /**
     * 商品详情
     */
    public function get_detail() {
        global $_W, $_GPC;
        $result = array();

        $openid =$_W['openid'];
        $uniacid = $_W['uniacid'];
        $id = intval($_GPC['id']);

        if(empty($id)){
            app_error(AppError::$ParamsError);
        }

        //多商户
        $merch_plugin = p('merch');
        $merch_data = m('common')->getPluginset('merch');
        if ($merch_plugin && $merch_data['is_openmerch']) {
            $is_openmerch = 1;
        } else {
            $is_openmerch = 0;
        }

        //商品
        $goods = pdo_fetch("select * from " . tablename('ewei_shop_goods') . " where id=:id and uniacid=:uniacid limit 1", array(':id' => $id, ':uniacid' => $_W['uniacid']));

        $member = m('member')->getMember($openid);

        $showlevels = $goods['showlevels']!='' ? explode(',', $goods['showlevels']) : array();
        $showgroups = $goods['showgroups']!='' ? explode(',', $goods['showgroups']) : array();

        $showgoods = 0;
        if(!empty($member)){
            //会员组有多个的时候 应该要用数组
            $member_groupid = $member['groupid']!='' ? explode(',', $member['groupid']) : array();
            if((!empty($showlevels)&&in_array($member['level'], $showlevels)) || (empty($showlevels) && empty($showgroups)) || (!empty($showgroups) && array_intersect($member_groupid, $showgroups))){
                $showgoods = 1;
            }
        }else{
            if(empty($showlevels) && empty($showgroups)){
                $showgoods = 1;
            }
        }

        if (empty($goods) || empty($showgoods)){
            app_error(AppError::$GoodsNotFound );
        }

        $merchid = $goods['merchid'];
        if (!empty($is_openmerch)){
            //判断多商户商品是否通过审核
            if ($merchid > 0 && $goods['checked'] == 1) {
                app_error(AppError::$GoodsNotChecked );
            }
        }
        $goods['sales'] = $goods['sales'] + $goods['salesreal'];

        $goods['buycontentshow'] = 0;
        if ($goods['buyshow'] == 1) {
            $sql = "select o.id from " . tablename('ewei_shop_order') . " o left join " . tablename('ewei_shop_order_goods') . " g on o.id = g.orderid";
            $sql .= " where o.openid=:openid and g.goodsid=:id and o.status>0 and o.uniacid=:uniacid limit 1";
            $buy_goods = pdo_fetch($sql, array(':openid' => $openid, ':id' => $id, ':uniacid' => $_W['uniacid']));
            if (!empty($buy_goods)) {
                $goods['buycontentshow'] = 1;
                $goods['buycontent'] = m('common')->html_to_images($goods['buycontent']);
            }
        }
        $goods['unit'] = empty($goods['unit'])?'件':$goods['unit'];

        //使用的快递是否有不配送区域
        $citys = m('dispatch')->getNoDispatchAreas($goods);
        if (!empty($citys) && is_array($citys)) {
            $has_city = 1;
        } else {
            $has_city = 0;
        }
        $goods['citys'] = $citys;
        $goods['has_city'] = $has_city;

        $goods['seckillinfo'] = false;
        $seckill  = p('seckill');
        if( $seckill){
            $time = time();
            $seckillinfo = $seckill->getSeckill($goods['id'],0,false);
            if(!empty($seckillinfo)){
                if($time >= $seckillinfo['starttime'] && $time<$seckillinfo['endtime']){
                    $seckillinfo['status'] = 0;
                    unset($_SESSION[$id . '_log_id']);
                    unset($_SESSION[$id . '_task_id']);
                    unset($log_id);
                }elseif( $time < $seckillinfo['starttime'] ){
                    $seckillinfo['status'] = 1;
                }else {
                    $seckillinfo['status'] = -1;
                }
            }
            $goods['seckillinfo']=$seckillinfo;
        }

        //运费
        $goods['dispatchprice'] = $this->getGoodsDispatchPrice($goods,$seckillinfo);

        //判断是否支持同城配送
        $goods['city_express_state']=1;
        $city_express = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_city_express') . " WHERE uniacid=:uniacid and merchid=0 limit 1", array(':uniacid' => $_W['uniacid']));
        //没设置同城配送或者禁用时
        if(empty($city_express)||$city_express['enabled']==0||$goods['merchid']>0 || $goods['type']!=1){
            $goods['city_express_state']=0;
        }elseif(empty($city_express['is_dispatch'])){
            $goods['dispatchprice']=array('min'=>$city_express['start_fee'],'max'=>$city_express['fixed_fee']);
        }
        //幻灯片
        $thumbs = iunserializer($goods['thumb_url']);

        if(empty($thumbs)){
            $thumbs = array( $goods['thumb'] );
            if (!empty($goods['thumb_first'])&&!empty($goods['thumb'])) {
                $thumbs =array_merge( array($goods['thumb']), $thumbs );
            }
            if(is_array($thumbs)&&count($thumbs)==2){
                $thumbs =  array_unique($thumbs);
            }
            $thumbs = array_values($thumbs);
        }else{
            if (!empty($goods['thumb_first'])&&!empty($goods['thumb'])) {
                $thumbs =array_merge( array($goods['thumb']), $thumbs );
            }
            $thumbs = array_values($thumbs);
        }

        $goods['thumbs'] = set_medias( $thumbs );
//        $thumbsHeight = array();
//        load()->func('communication');
//        foreach($goods['thumbs'] as $gk => $gv){
//            $image_content = ihttp_get($gv);
//            $img_info = imagecreatefromstring($image_content['content']);
//            $thumbsHeight[$gk] = imagesy($img_info);
//            $thumbsWH[$gk]['height'] = imagesy($img_info);
//            $thumbsWH[$gk]['width'] = imagesx($img_info);
//        }
//
//        foreach($thumbsWH as $thk => $thv){
//            if($thv['height'] == max($thumbsHeight)){
//                $goods['thumbMaxHeight'] += $thv['height'];
//                $goods['thumbMaxWidth'] += $thv['width'];
//            }
//        }
        $goods['thumbMaxWidth'] = 750;
        $goods['thumbMaxHeight'] = 750;
        $goods['video'] = tomedia($goods['video']);
        if(strexists($goods['video'], 'v.qq.com/iframe/player.html')){
            $videourl = $this->model->getQVideo($goods['video']);
            if(!is_error($videourl)){
                $goods['video'] = $videourl;
            }
        }

        if(!empty($goods['thumbs']) && is_array($goods['thumbs'])){
            $new_thumbs = array();
            foreach ($goods['thumbs'] as $i=>$thumb){
                $new_thumbs[] = $thumb;
            }
            $goods['thumbs'] = $new_thumbs;
        }

        //规格specs
        $specs = pdo_fetchall('select * from ' . tablename('ewei_shop_goods_spec') . " where goodsid=:goodsid and  uniacid=:uniacid order by displayorder asc", array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
        $spec_titles = array();
        foreach ($specs as $key => $spec) {
            if ($key >= 2) {
                break;
            }
            $spec_titles[] = $spec['title'];
        }
        if($goods['hasoption']>0){
            $goods['spec_titles'] = implode('、', $spec_titles);
        }else{
            $goods['spec_titles'] = '';
        }

        //参数
        $goods['params'] = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_goods_param') . " WHERE uniacid=:uniacid and goodsid=:goodsid order by displayorder asc", array(':uniacid' => $uniacid, ":goodsid" => $goods['id']));

        $goods = set_medias($goods, 'thumb');
        $goods['canbuy'] = !empty($goods['status']) && empty($goods['deleted']) ? 1 : 0;
        $goods['cannotbuy'] = "";

        if ($goods['total'] <= 0) {
            $goods['canbuy'] = 0;
            $goods['cannotbuy'] = "商品库存不足";
        }

        if ($goods['isendtime'] > 0 && $goods['endtime'] > 0 && $goods['endtime'] < time()) {
            $goods['canbuy'] = 0;
            $goods['cannotbuy'] ="商品已过期";
        }
        $goods['timestate'] = '';

        //判断用户最大购买量
        $goods['userbuy'] = '1';
        if ($goods['usermaxbuy'] > 0) {
            $order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og '
                . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id '
                . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $goods['id'], ':uniacid' => $uniacid, ':openid' => $openid));
            if ($order_goodscount >= $goods['usermaxbuy']) {
                $goods['userbuy'] = 0;
                $goods['canbuy']  = 0;
                $goods['cannotbuy'] = "超出最大购买数量";
            }
        }
        $levelid = $member['level'];
        $groupid = $member['groupid'];

        //判断会员权限
        $goods['levelbuy'] = '1';
        if ($goods['buylevels'] != '') {
            $buylevels = explode(',', $goods['buylevels']);
            if (!in_array($levelid, $buylevels)) {
                $goods['levelbuy'] = 0;
                $goods['canbuy']  = 0;
                $goods['cannotbuy'] = "购买级别不够";
            }
        }

        //会员组权限
        $goods['groupbuy'] = '1';
        if ($goods['buygroups'] != '') {
            $buygroups = explode(',', $goods['buygroups']);
            if (!array_intersect($member_groupid, $buygroups)) {
                $goods['groupbuy'] = 0;
                $goods['canbuy']  = 0;
                $goods['cannotbuy'] = "所在会员组无法购买";
            }
        }

        //判断限时购
        $goods['timebuy'] = '0';
        if ($goods['istime'] == 1) {
            if (time() < $goods['timestart']) {
                $goods['timebuy'] = '-1';
                $goods['canbuy']  = 0;
                $goods['cannotbuy'] = "限时购未开始";
            } else if (time() > $goods['timeend']) {
                $goods['timebuy'] = '1';
                $goods['canbuy']  = 0;
                $goods['cannotbuy'] = "限时购已结束";
            }
        }
        //判断赠品
        if($goods['status'] ==2){
            $goods['canbuy']  = 0;
            $goods['cannotbuy'] = "赠品无法购买";
        }
        //记次时商品核销时间限制提示
        $goods['timeout'] = false;  //超过时间限制
        $goods['access_time'] = false;  //临近时间限制
        if($goods['type'] == 5){
            if($goods['verifygoodslimittype'] == 1){
                $limittime = $goods['verifygoodslimitdate'];
                $now = time();
                if($limittime < time()){
                    $goods['timeout'] = true;
                    $goods['hint'] = '您选择的记次时商品的使用时间已经失效，无法购买！';
                }else if(($limittime-$now) > 1800 && ($limittime-$now) < 7200){
                    $goods['access_time'] = true;
                    $goods['hint'] = '您选择的记次时商品到期日期是"'.date('Y-m-d H:i:s',$limittime).'",请确保有足够的时间抵达核销门店进行核销，以免耽误您的使用。';
                }else if(($limittime-$now) < 1800){
                    $goods['timeout'] = true;
                    $goods['hint'] = '您选择的记次时商品的使用时间即将失效，无法购买！';
                }
            }
        }

        //是否全返商品
        $isfullback = false;
        if($goods['isfullback']){
            $fullbackgoods = pdo_fetch("SELECT * FROM ".tablename('ewei_shop_fullback_goods')." WHERE uniacid = :uniacid and goodsid = :goodsid and status =1 limit 1 ",array(':uniacid'=>$uniacid,':goodsid'=>$id));
            if(!empty($fullbackgoods)){
                $isfullback = true;
            }
            if($goods['hasoption']==1){
                $fullprice = pdo_fetch("select min(allfullbackprice) as minfullprice,max(allfullbackprice) as maxfullprice,min(allfullbackratio) as minfullratio
                            ,max(allfullbackratio) as maxfullratio,min(fullbackprice) as minfullbackprice,max(fullbackprice) as maxfullbackprice
                            ,min(fullbackratio) as minfullbackratio,max(fullbackratio) as maxfullbackratio,min(`day`) as minday,max(`day`) as maxday
                            from ".tablename('ewei_shop_goods_option')." where goodsid = :goodsid",array(':goodsid'=>$id));
                $fullbackgoods['minallfullbackallprice'] = $fullprice['minfullprice'];
                $fullbackgoods['maxallfullbackallprice'] = $fullprice['maxfullprice'];
                $fullbackgoods['minallfullbackallratio'] = $fullprice['minfullratio'];
                $fullbackgoods['maxallfullbackallratio'] = $fullprice['maxfullratio'];
                $fullbackgoods['minfullbackprice'] = $fullprice['minfullbackprice'];
                $fullbackgoods['maxfullbackprice'] = $fullprice['maxfullbackprice'];
                $fullbackgoods['minfullbackratio'] = $fullprice['minfullbackratio'];
                $fullbackgoods['maxfullbackratio'] = $fullprice['maxfullbackratio'];
                $fullbackgoods['fullbackratio'] = $fullprice['minfullbackratio'];
                $fullbackgoods['fullbackprice'] = $fullprice['minfullbackprice'];
                $fullbackgoods['minday'] = $fullprice['minday'];
                $fullbackgoods['maxday'] = $fullprice['maxday'];
            }else{
                $fullbackgoods['maxallfullbackallprice'] = $fullbackgoods['minallfullbackallprice'];
                $fullbackgoods['maxallfullbackallratio'] = $fullbackgoods['minallfullbackallratio'];
                $fullbackgoods['minday'] = $fullbackgoods['day'];
            }
        }
        $goods['isfullback'] = $isfullback;
        $goods['fullbackgoods'] = $fullbackgoods;
        $goods['fullbacktext'] = m('sale')->getFullBackText();

        //赠品活动
        $isgift = 0;
        $gifts = array();
        $giftgoods = array();
        $grftarray = array();
        $i = 0;
        $gifts = pdo_fetchall("select id,goodsid,giftgoodsid,thumb,title from ".tablename('ewei_shop_gift')." where uniacid = :uniacid and activity = 2 and status = 1 and starttime <= :starttime and endtime >= :endtime ",array(':uniacid'=>$uniacid,':starttime'=>time(),':endtime'=>time()));
        foreach($gifts as $key => $value){
            $gid = explode(",",$value['goodsid']);
            foreach ($gid as $ke => $val){
                if($val==$id){
                    $giftgoods = explode(",",$value['giftgoodsid']);
                    foreach($giftgoods as $k => $val){
                        $giftdata= pdo_fetch("select id,title,thumb,marketprice from ".tablename('ewei_shop_goods')." where uniacid = :uniacid and deleted = 0 and total > 0 and status = 2 and id = :id ",array(':uniacid'=>$uniacid,':id'=>$val));
                        if(!empty($giftdata)){
                            $isgift = 1;
                            $gifts[$key]['gift'][$k] = $giftdata;
                            $gifttitle = !empty($gifts[$key]['gift'][$k]['title']) ? $gifts[$key]['gift'][$k]['title'] : '赠品';
                            $gifts[$key]['gift'][$k] = set_medias($gifts[$key]['gift'][$k],array('thumb'));
                        }
                    }
                }
            }
            if(empty($gifts[$key]['gift'])){
                unset($gifts[$key]);
            }else{
                $grftarray[$i] = $gifts[$key];
                $i++;
            }
        }
        $grftarray = set_medias($grftarray,array('thumb'));
        $goods['isgift'] = $isgift;
        $goods['gifts'] = $grftarray;

        //是否可以加入购物车
        $goods['canAddCart'] = 1;
        if ($goods['isverify'] == 2 || $goods['type'] == 2 || $goods['type'] == 3 || !empty($grftarray) || !empty($seckillinfo)|| $goods['status'] ==2) {
            $goods['canAddCart'] = 0;
        }
        //营销活动
        $enoughs = com_run('sale::getEnoughs'); //立减
        $enoughfree = com_run('sale::getEnoughFree'); //满包邮
        $goods_nofree = com_run('sale::getEnoughsGoods'); //满额不包邮商品

        if ($is_openmerch == 1 && $goods['merchid'] > 0) {
            $merch_set = $merch_plugin->getSet('sale', $goods['merchid']);
            if ($merch_set['enoughfree']){
                $enoughfree = $merch_set['enoughorder'];
                if ($merch_set['enoughorder'] == 0){
                    $enoughfree = -1;
                }
            }
        }

        if ($enoughfree && $goods['minprice'] > $enoughfree && empty($seckillinfo)) {
            $goods['dispatchprice'] = 0;
        }
        $goods['hasSales'] = 0;
        if ($goods['ednum'] > 0 || $goods['edmoney'] > 0) {
            $goods['hasSales'] = 1;
        }
        if ($enoughfree || ($enoughs && count($enoughs) > 0)) {
            $goods['hasSales'] = 1;
        }
        if(!empty($goods_nofree)){
            if(in_array($id,$goods_nofree)){
                $enoughfree = 0;  //设置单独商品满额不包邮
            }
        }
        $goods['enoughfree'] = $enoughfree;
        $goods['enoughs'] = $enoughs;

        //价格显示
        $minprice = $goods['minprice']; $maxprice = $goods['maxprice'] ;

        $level = m('member')->getLevel($openid);
        $memberprice = m('goods')->getMemberPrice($goods, $level);

        if($goods['isdiscount'] && $goods['isdiscount_time']>=time()){
            $goods['oldmaxprice'] = $maxprice;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'],true);
            $prices = array();

            if (!isset($isdiscount_discounts['type']) || empty($isdiscount_discounts['type'])) {
                //统一促销
                $level = m('member')->getLevel($openid);
                $prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
                $prices[] = $prices_array['price'];
            } else {
                //详细促销
                $goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid);
                $prices = $goods_discounts['prices'];
            }
            $minprice = min($prices);
            $maxprice = max($prices);
        }

        $goods['minprice'] = (float)$minprice; $goods['maxprice'] =(float)$maxprice;

        //是否显示商品评论
        $goods['getComments'] = empty($_W['shopset']['trade']['closecommentshow']);
        //配套服务
        $goods['hasServices'] = $goods['cash'] || $goods['seven'] || $goods['repair'] || $goods['invoice'] || $goods['quality'];
        $goods['services'] = array();
        if($goods['cash']){
            $goods['services'][] = "货到付款";
        }
        if($goods['quality']){
            $goods['services'][] = "正品保证";
        }
        if($goods['seven']){
            $goods['services'][] = "7天无理由退换";
        }
        if($goods['invoice']){
            $goods['services'][] = "发票";
        }
        if($goods['repair']){
            $goods['services'][] = "保修";
        }

        // 旧标签
        $labelstyle = pdo_fetch("SELECT id,uniacid,style FROM " . tablename('ewei_shop_goods_labelstyle') . " WHERE uniacid=:uniacid LIMIT 1", array(
            ':uniacid'=>$uniacid
        ));
        if(json_decode($goods['labelname'],true)){
            $labelname = json_decode($goods['labelname'],true);
        }else{
            $labelname = unserialize($goods['labelname']);
        }
        $goods['labelname'] = $labelname;
        $goods['labelstyle'] = $labelstyle;

        // 新标签
        $labellist = $goods['services'];
        if(is_array($labelname)){
            $labellist = array_merge($labellist, $labelname);
        }
        $goods['labels'] = array(
            'style'=>is_array($labelstyle)? intval($labelstyle['style']): 0,
            'list'=>$labellist
        );

        //是否收藏了
        $goods['isfavorite'] = m('goods')->isFavorite($id);
        //购物车数量
        $goods['cartcount'] = m('goods')->getCartCount();
        //浏览量 + 浏览记录
        m('goods')->addHistory($id);

        //店铺信息
        $shop = set_medias(m('common')->getSysset('shop'), 'logo');
        //店铺信息
        $shop['url'] = mobileUrl('',null);
        $mid = intval($_GPC['mid']);
        //判断是否开启分销
        $opencommission = false;
        if (p('commission')) {
            if (empty($member['agentblack'])) { //不在黑名单
                $cset = p('commission')->getSet();
                $opencommission = intval($cset['level']) > 0;
                //是否是小店
                if ($opencommission) {
                    if (empty($mid)) {
                        if ($member['isagent'] == 1 && $member['status'] == 1) {
                            $mid = $member['id'];
                        }
                    }
                    if (!empty($mid)) {
                        if (empty($cset['closemyshop'])) {
                            $shop = set_medias( p('commission')->getShop($mid), 'logo');
                            $shop['url'] = mobileUrl('commission/myshop', array('mid' => $mid),true);
                        }
                    }

                }
            }
        }
        if (empty($this->merch_user))
        {
            $merch_flag = 0;
            if ($is_openmerch == 1 && $goods['merchid'] > 0) {
                $merch_user = pdo_fetch("select * from ".tablename('ewei_shop_merch_user')." where id=:id limit 1",array(':id'=>intval($goods['merchid'])));
                if (!empty($merch_user)) {
                    $shop = $merch_user;
                    $merch_flag = 1;
                }
            }

            if ($merch_flag == 1) {
                $shopdetail = array(
                    'logo' => !empty($goods['detail_logo']) ? tomedia($goods['detail_logo']) : tomedia($shop['logo']),
                    'shopname' => !empty($goods['detail_shopname']) ? $goods['detail_shopname'] : $shop['merchname'],
                    'description' =>!empty($goods['detail_totaltitle']) ? $goods['detail_totaltitle'] : $shop['desc'],
                    'btntext1' => trim($goods['detail_btntext1']),
                    'btnurl1' => !empty($goods['detail_btnurl1']) ? $goods['detail_btnurl1'] : mobileUrl('goods'),
                    'btntext2' => trim($goods['detail_btntext2']),
                    'btnurl2' => !empty($goods['detail_btnurl2']) ? $goods['detail_btnurl2'] : mobileUrl('merch',array('merchid'=> $goods['merchid']))
                );
            } else {
                $shopdetail = array(
                    'logo' => !empty($goods['detail_logo']) ? tomedia($goods['detail_logo']) : $shop['logo'],
                    'shopname' => !empty($goods['detail_shopname']) ? $goods['detail_shopname'] : $shop['name'],
                    'description' =>!empty($goods['detail_totaltitle']) ? $goods['detail_totaltitle'] : $shop['description'],
                    'btntext1' => trim($goods['detail_btntext1']),
                    'btnurl1' => !empty($goods['detail_btnurl1']) ? $goods['detail_btnurl1'] : mobileUrl('goods'),
                    'btntext2' => trim($goods['detail_btntext2']),
                    'btnurl2' => !empty($goods['detail_btnurl2']) ? $goods['detail_btnurl2'] : $shop['url']
                );
            }
            $param = array(':uniacid'=>$_W['uniacid']);
            if ($merch_flag == 1) {
                $sqlcon = " and merchid=:merchid";
                $param[':merchid'] = $goods['merchid'];
            }
            //统计
            if (empty($shop['selectgoods'])) {
                $statics = array(
                    'all'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid {$sqlcon} and status=1 and deleted=0", $param),
                    'new'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid {$sqlcon} and isnew=1 and status=1 and deleted=0", $param),
                    'discount'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid {$sqlcon} and isdiscount=1 and status=1 and deleted=0", $param)
                );
            } else {
                $goodsids = explode(",", $shop['goodsids']);
                $statics = array(
                    'all'=>count($goodsids),
                    'new'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid {$sqlcon} and id in( {$shop['goodsids']} ) and isnew=1 and status=1 and deleted=0", $param),
                    'discount'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid {$sqlcon} and id in( {$shop['goodsids']} ) and isdiscount=1 and status=1 and deleted=0", $param)
                );
            }
        }
        else {
            if ($goods['checked'] == 1)
            {
                app_error(AppError::$GoodsNotChecked);
            }
            $shop = $this->merch_user;
            $shopdetail = array(
                'logo' => !empty($goods['detail_logo']) ? tomedia($goods['detail_logo']) : tomedia($shop['logo']),
                'shopname' => !empty($goods['detail_shopname']) ? $goods['detail_shopname'] : $shop['merchname'],
                'description' =>!empty($goods['detail_totaltitle']) ? $goods['detail_totaltitle'] : $shop['desc'],
                'btntext1' => trim($goods['detail_btntext1']),
                'btnurl1' => !empty($goods['detail_btnurl1']) ? $goods['detail_btnurl1'] : mobileUrl('goods'),
                'btntext2' => trim($goods['detail_btntext2']),
                'btnurl2' => !empty($goods['detail_btnurl2']) ? $goods['detail_btnurl2'] : mobileUrl('merch',array('merchid'=> $goods['merchid']))
            );

            //统计
            if (empty($shop['selectgoods'])) {
                $statics = array(
                    'all'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid and merchid=:merchid and status=1 and deleted=0",array(':uniacid'=>$_W['uniacid'],':merchid'=>$goods['merchid'])),
                    'new'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid and merchid=:merchid and isnew=1 and status=1 and deleted=0",array(':uniacid'=>$_W['uniacid'],':merchid'=>$goods['merchid'])),
                    'discount'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid and merchid=:merchid and isdiscount=1 and status=1 and deleted=0",array(':uniacid'=>$_W['uniacid'],':merchid'=>$goods['merchid']))
                );
            } else {
                $goodsids = explode(",", $shop['goodsids']);
                $statics = array(
                    'all'=>count($goodsids),
                    'new'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid and merchid=:merchid and id in( {$shop['goodsids']} ) and isnew=1 and status=1 and deleted=0",array(':uniacid'=>$_W['uniacid'],':merchid'=>$goods['merchid'])),
                    'discount'=>pdo_fetchcolumn('select count(1) from '.tablename('ewei_shop_goods')." where uniacid=:uniacid and merchid=:merchid and id in( {$shop['goodsids']} ) and isdiscount=1 and status=1 and deleted=0",array(':uniacid'=>$_W['uniacid'],':merchid'=>$goods['merchid']))
                );
            }
        }

        //分享
        $goodsdesc = !empty($goods['description']) ? $goods['description']  : $goods['subtitle'];
        $_W['shopshare'] = array(
            'title' => !empty($goods['share_title']) ? $goods['share_title'] : $goods['title'],
            'imgUrl' => !empty($goods['share_icon']) ? tomedia($goods['share_icon']) : tomedia($goods['thumb']),
            'desc' => !empty($goodsdesc) ? $goodsdesc  : $_W['shopset']['shop']['name'],
            'link' => mobileUrl('app/share', array('type'=>'goods', 'id' => $goods['id']),true)
        );
        $com = p('commission');
        if ($com) {
            $cset = $_W['shopset']['commission'];
            if (!empty($cset)) {
                if ($member['isagent'] == 1 && $member['status'] == 1) {
                    $_W['shopshare']['link'] = mobileUrl('app/share', array('type'=>'goods', 'id' => $goods['id'], 'mid' => $member['id']),true);
                } else if (!empty($_GPC['mid'])) {
                    $_W['shopshare']['link'] = mobileUrl('app/share', array('type'=>'goods', 'id' => $goods['id'], 'mid' => $_GPC['mid']),true);
                }
            }
            //            获取商品佣金
            if($goods['nocommission']==0){
                $glevel = $this->getLevel($openid);
                if(p('seckill')){
                    if (p('seckill')->getSeckill($goods['id'])){
//                    秒杀
                        $goods['seecommission'] = 0;
                    }
                }
                if($goods['bargain']>0){
                    //        bargain 砍价
                    $goods['seecommission'] = 0;
                }
                $goods['seecommission'] = $this->getCommission($goods,$glevel,$cset);
                if($goods['seecommission']>0){
                    $goods['seecommission'] = round($goods['seecommission'],2);
                }
            }else{
                $goods['seecommission'] = 0;
            }
            $goods['cansee'] = $cset['cansee'];
            $goods['seetitle'] = $cset['seetitle'];
        }else{
            $goods['cansee'] = 0;
        }

        //核销门店
        $stores = array();
        if ($goods['isverify'] == 2) {
            $storeids = array();
            if (!empty($goods['storeids'])) {
                $storeids = array_merge(explode(',', $goods['storeids']), $storeids);
            }

            if (empty($storeids)) {
                //全部门店
                if ($merchid > 0) {
                    $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 ', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                } else {
                    $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1', array(':uniacid' => $_W['uniacid']));
                }
            } else {
                if ($merchid > 0) {
                    $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
                } else {
                    $stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1', array(':uniacid' => $_W['uniacid']));
                }
            }
        }

        unset( $goods['pcate'], $goods['ccate'], $goods['tcate'], $goods['costprice'], $goods['originalprice'], $goods['totalcnf'], $goods['salesreal'], $goods['score'], $goods['taobaoid'], $goods['taotaoid'], $goods['taobaourl'], $goods['updatetime'], $goods['noticeopenid'], $goods['noticetype'], $goods['ccates'], $goods['pcates'], $goods['tcates'], $goods['cates'], $goods['artid'], $goods['allcates'], $goods['hascommission'], $goods['commission1_rate'], $goods['commission1_pay'], $goods['commission2_rate'], $goods['commission2_pay'], $goods['commission3_rate'], $goods['commission3_pay'], $goods['commission_thumb'], $goods['commission'], $goods['needfollow'], $goods['followurl'], $goods['followtip'], $goods['sharebtn'], $goods['keywords'], $goods['timestate'], $goods['nocommission'], $goods['hidecommission'], $goods['diysave'], $goods['diysaveid'], $goods['deduct2'], $goods['shopid'], $goods['shorttitle'], $goods['diyformtype'], $goods['diyformid'], $goods['diymode'], $goods['discounts'], $goods['verifytype'], $goods['diyfields'], $goods['groupstype'], $goods['merchsale'], $goods['manydeduct'], $goods['checked'], $goods['goodssn'], $goods['productsn'], $goods['isdiscount_discounts'], $goods['isrecommand'], $goods['dispatchtype'], $goods['dispatchid'], $goods['storeids'], $goods['thumb_url'],$goods['share_icon'],$goods['share_title']);

        if(!empty($goods['thumb_url'])){
            $goods['thumb_url'] = iunserializer($goods['thumb_url']);
        }
        $goods['stores'] =  $stores;

        if(!empty($shopdetail)){
            $shopdetail['btntext1'] = !empty($shopdetail['btntext1']) ? $shopdetail['btntext1'] : "全部商品";
            $shopdetail['btntext2'] = !empty($shopdetail['btntext2']) ? $shopdetail['btntext2'] : "进店逛逛";
            $shopdetail['btnurl1'] = $this->model->getUrl($shopdetail['btnurl1']);
            $shopdetail['btnurl2'] = $this->model->getUrl($shopdetail['btnurl2']);

            $shopdetail['static_all'] = $statics['all'];
            $shopdetail['static_new'] = $statics['new'];
            $shopdetail['static_discount'] = $statics['discount'];
        }
        $shopdetail = set_medias($shopdetail, 'logo');
        $goods['shopdetail'] = $shopdetail;

        $goods['share'] = $_W['shopshare'];

        $goods['memberprice'] = '';
        if(empty($goods['isdiscount']) || (!empty($goods['isdiscount']) &&$goods['isdiscount_time']<time())){
            if(!empty($memberprice) && $memberprice!=$goods['minprice'] && !empty($level)){
                $goods['memberprice'] = array(
                    'levelname'=>$level['levelname'],
                    'price'=>$memberprice
                );
            }
        }

        // 获取商品可领取优惠券
        $goods['coupons'] = array();
        if(com('coupon')) {
            $goods['coupons'] =$this->getCouponsbygood($goods['id']);
        }

        if($goods['type']==3){

        }

        $goods['presellsendstatrttime'] = date('m月d日',$goods['presellsendstatrttime']);
        $goods['endtime'] = date('Y-m-d H:i:s',$goods['endtime']);
        $goods['isdiscount_date'] = date('Y-m-d H:i:s',$goods['isdiscount_time']);
        $goods['productprice'] = (float)$goods['productprice'];
        $goods['credittext'] = $_W['shopset']['trade']['credittext'];
        $goods['moneytext'] = $_W['shopset']['trade']['moneytext'];
        //print_r($goods);
        $goods['content'] = m('common')->html_to_images($goods['content']);

        // 客服按钮
        $goods['navbar'] = intval($_W['shopset']['app']['navbar']);
        $goods['customer'] = intval($_W['shopset']['app']['customer']);
        $goods['phone'] = intval($_W['shopset']['app']['phone']);
		if(!empty($goods['content'])) {
		$goods['content'] = preg_replace('/ style="[^\"]*"/i','',$goods['content']);
		$goods['content'] = preg_replace('/ class="[^\"]*"/i','',$goods['content']);
		}
        if(!empty($goods['customer'])){
            $goods['customercolor'] = empty($_W['shopset']['app']['customercolor'])? '#ff5555': $_W['shopset']['app']['customercolor'];
        }

        if(!empty($goods['phone'])){
            $goods['phonecolor'] = empty($_W['shopset']['app']['phonecolor'])? '#ff5555': $_W['shopset']['app']['phonecolor'];
            $goods['phonenumber'] = empty($_W['shopset']['app']['phonenumber'])? '#ff5555': $_W['shopset']['app']['phonenumber'];
        }

        if(!empty($goods['ispresell'])){
            //显示预售时间和能否购买 lgt
            $goods['ispresellshow'] = 1;
            if(!empty($goods['preselltimestart'])){
                if($goods['preselltimestart'] > time()){
                    $goods['canbuy'] = 0;
                    $goods['preselltitle'] = '距离预售开始';
                }else if(($goods['preselltimestart'] < time() && $goods['preselltimeend'] >time()) || ($goods['preselltimestart'] < time() && empty($goods['preselltimeend'])) ){
                    $goods['canbuy'] = 1;
                    $goods['preselltitle'] = '距离预售结束';
                }else if($goods['preselltimeend'] < time() && !empty($goods['preselltimeend'])){
                    $times = $goods['presellovertime'] * 60 * 60 * 24 + $goods['preselltimeend'];
                    if($goods['presellover']>0 && $times <= time()){
                        $goods['canbuy'] = 1;
                        $goods['ispresellshow'] = 0;
                    }else{
                        $goods['ispresellshow'] = 0;
                        $goods['canbuy'] = 0;
                    }
                }
            }
            if($goods['ispresell']>0 && ($goods['preselltimeend'] == 0 || $goods['preselltimeend'] > time())){
                //预售商品价格处理 lgt
                if(!empty($goods['hasoption'])){
                    $presell = pdo_fetch("select min(presellprice) as minprice,max(presellprice) as maxprice from ".tablename('ewei_shop_goods_option')." where goodsid = ".$id);
                    $goods['minpresellprice'] = $presell['minprice'];
                    $goods['maxpresellprice'] = $presell['maxprice'];
                }
            }

            $goods['preselldatestart'] = empty($goods['preselltimestart'])? 0: date('Y-m-d H:i:s', $goods['preselltimestart']);
            $goods['preselldateend'] = empty($goods['preselltimeend'])? 0: date('Y-m-d H:i:s', $goods['preselltimeend']);
        }
        //套餐
        $package_goods = array();
        $package_goods = pdo_fetch("select pg.id,pg.pid,pg.goodsid,p.displayorder,p.title from ".tablename('ewei_shop_package_goods')." as pg
                        left join ".tablename('ewei_shop_package')." as p on pg.pid = p.id
                        where pg.uniacid = ".$uniacid." and pg.goodsid = ".$id." and  p.starttime <= ".time()." and p.endtime >= ".time()." and p.deleted = 0 and p.status = 1 ORDER BY p.displayorder desc,pg.id desc limit 1 ");
        if($package_goods['pid']){
            $packages = pdo_fetchall("SELECT id,title,thumb,packageprice FROM ".tablename('ewei_shop_package_goods')."
                    WHERE uniacid = ".$uniacid." and pid = ".$package_goods['pid']."  ORDER BY id DESC");
            $packages = set_medias($packages,array('thumb'));
        }
        $goods['packagegoods'] = $package_goods;


        //判断包邮
        $hasSales = false;
        if ($goods['ednum'] > 0 || $goods['edmoney'] > 0) {
            $hasSales = true;
        }
        if ($enoughfree || ($enoughs && count($enoughs) > 0)) {
            $hasSales = true;
        }

        $activity=array();
        //商城满减
        if($enoughs && count($enoughs)>0 && empty($seckillinfo)){
            $activity['enough']=$enoughs;
        }

        //商户满减
        if(!empty($merch_set['enoughdeduct']) && empty($seckillinfo)){

            //合并第一条多商户满减数据
            $one=array(array('enough'=>$merch_set['enoughmoney'],'give'=>$merch_set['enoughdeduct']));
            $merch_set['enoughs']=array_merge_recursive($one,$merch_set['enoughs']);

            $activity['merch_enough']=$merch_set['enoughs'];
        }
        //包邮
        if($hasSales && empty($seckillinfo)){
            if((!is_array($goods['dispatchprice']) && $goods['type']==1 && $goods['isverify']!=2 && $goods['dispatchprice']==0) || (($enoughfree && $enoughfree==-1) || ($enoughfree>0 || $goods['ednum']>0 || $goods['edmoney']>0))){

                if(!is_array($goods['dispatchprice'])){
                    if($goods['type']==1 && $goods['isverify']!=2){
                        if($goods['dispatchprice']==0){
                            $activity['postfree']['goods']=true;
                        }
                    }
                }
                if($enoughfree>0 && $goods['minprice']<$enoughfree){
                    $activity['postfree']['goods']=false;
                }
                if($goods['edmoney']>0 && $goods['minprice']<$goods['edmoney']){
                    $activity['postfree']['goods']=false;
                }
                if($enoughfree && $enoughfree==-1){
                    if(!empty($merch_set['enoughfree'])){
                        $activity['postfree']['scope']='本店';
                    }else{
                        $activity['postfree']['scope']='全场';
                    }
                }else{

                    if($goods['ednum']>0){
                        $activity['postfree']['num']=$goods['ednum'];
                        $activity['postfree']['unit']=empty($goods['unit'])?'件':$goods['unit'];
                    }

                    if($goods['edmoney']>0){
                        $activity['postfree']['price']=$goods['edmoney'];
                    }

                    if($enoughfree){
                        if(!empty($merch_set['enoughfree'])){
                            $activity['postfree']['scope']='本店';
                        }else{
                            $activity['postfree']['scope']='全场';
                        }
                    }

                    $activity['postfree']['enoughfree']=$enoughfree;
                }
            }
        }
        //积分抵扣
        if(!empty($goods['deduct']) && $goods['deduct'] != '0.00'){
            $activity['credit']['deduct']=$goods['deduct'];//最高抵扣多少钱
        }

        //积分赠送
        if(!empty($goods['credit'])){
            $activity['credit']['give']=$goods['credit'];//赠送多少积分
        }

        //复购
        if(floatval($goods['buyagain'])>0 && empty($seckillinfo)){
            $activity['buyagain']['discount']=$goods['buyagain'];//打几折
            $activity['buyagain']['buyagain_sale']=$goods['buyagain_sale'];//是否与其他优惠共享
        }

        //全返
        if( !empty($fullbackgoods) && $isfullback){

            if($fullbackgoods['type']>0){
                if($goods['hasoption'] > 0){
                    if($fullbackgoods['minallfullbackallratio'] == $fullbackgoods['maxallfullbackallratio']){
                        $activity['fullback']['all_enjoy']=$fullbackgoods['minallfullbackallratio'].'%';//最大最小价格相等，固定的折数
                    }else{
                        $activity['fullback']['all_enjoy']=$fullbackgoods['minallfullbackallratio'].'% ~ '.$fullbackgoods['maxallfullbackallratio'].'%';//折数区间
                    }

                    if($fullbackgoods['minfullbackratio'] == $fullbackgoods['maxfullbackratio']){
                        $activity['fullback']['enjoy']=price_format($fullbackgoods['minfullbackratio'],2).'%';
                    }else{
                        $activity['fullback']['enjoy']=price_format($fullbackgoods['minfullbackratio'],2).'% ~ '.price_format($fullbackgoods['maxfullbackratio'],2).'%';
                    }
                }else{
                    $activity['fullback']['all_enjoy']=$fullbackgoods['minallfullbackallratio'].'%';//没有多规格，固定的折数
                    $activity['fullback']['enjoy']=price_format($fullbackgoods['fullbackratio'],2).'%';
                }
            }else{
                if($goods['hasoption'] > 0){
                    if($fullbackgoods['minallfullbackallprice'] == $fullbackgoods['maxallfullbackallprice']){
                        $activity['fullback']['all_enjoy']='￥'.$fullbackgoods['minallfullbackallprice'];//最大最小价格相等，固定的价格
                    }else{
                        $activity['fullback']['all_enjoy']='￥'.$fullbackgoods['minallfullbackallprice'].' ~ ￥'.$fullbackgoods['maxallfullbackallprice'];//价格区间
                    }

                    if($fullbackgoods['minfullbackprice'] == $fullbackgoods['maxfullbackprice']){
                        $activity['fullback']['enjoy']='￥'.price_format($fullbackgoods['minfullbackprice'],2);
                    }else{
                        $activity['fullback']['enjoy']='￥'.price_format($fullbackgoods['minfullbackprice'],2).' ~ ￥'. price_format($fullbackgoods['maxfullbackprice'],2);
                    }

                }else{
                    $activity['fullback']['all_enjoy']='￥'.$fullbackgoods['minallfullbackallprice'];//没有多规格，固定的价格
                    $activity['fullback']['enjoy']='￥'.price_format($fullbackgoods['fullbackprice'],2);
                }
            }

            if($goods['hasoption'] > 0){
                if($fullbackgoods['minday'] == $fullbackgoods['maxday']){
                    $activity['fullback']['day']=$fullbackgoods['minday'];
                }else{
                    $activity['fullback']['day']=$fullbackgoods['minday'].' ~ '.$fullbackgoods['maxday'];
                }
            }else{
                $activity['fullback']['day']=$fullbackgoods['day'];
            }

            if($fullbackgoods['startday']>0){
                $activity['fullback']['startday']=$fullbackgoods['startday'];
            }
        }

        $goods['activity']=$activity;
        //判断是否支持同城配送
        $goods['city_express_state']=1;
        $city_express = pdo_fetch("SELECT * FROM " . tablename('ewei_shop_city_express') . " WHERE uniacid=:uniacid and merchid=0 limit 1", array(':uniacid' => $_W['uniacid']));
        //没设置同城配送或者禁用时
        if(empty($city_express)||$city_express['enabled']==0||$goods['merchid']>0 || $goods['type']!=1){
            $goods['city_express_state']=0;
        }

        if($goods['type'] == 9){
            $cycelset = m('common')->getSysset('cycelbuy');
            $goods['ahead_goods'] = $cycelset['ahead_goods'];
            $goods['scope'] = $cycelset['days'];
            $ahead = $cycelset['ahead_goods']*86400;
            $goods['showDate'] = date('Ymd',(time()+$ahead));
        }

        //价格显示
        $minprice = $goods['minprice'];
        $maxprice = $goods['maxprice'] ;

//        if($goods['isdiscount'] && $goods['isdiscount_time']>=time()){
//            $goods['oldmaxprice'] = $maxprice;
//            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'],true);
//            $prices = array();
//
//            if (!isset($isdiscount_discounts['type']) || empty($isdiscount_discounts['type'])) {
//                //统一促销
//                $level = m('member')->getLevel($openid);
//                $prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
//                $prices[] = $prices_array['price'];
//
//            } else {
//                //详细促销
//                $goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid, $options);
//                $prices = $goods_discounts['prices'];
//                $options = $goods_discounts['options'];
//            }
//            $minprice = min($prices);
//            $maxprice = max($prices);
//        }


        //如果是多规格，获取规格原价最大值
        if($goods['hasoption']>0){
            $productprice = pdo_fetchcolumn("select max(productprice) as productprice from ".tablename('ewei_shop_goods_option')." where goodsid = :goodsid",array(':goodsid'=>$id));
            if(!empty($productprice)){
                $goods['productprice']=$productprice;
            }
//      秒杀价显示   lgt
//            $productprice = pdo_fetch("select max(productprice) as productprice,min(productprice) as minProductprice from ".tablename('ewei_shop_goods_option')." where goodsid = :goodsid",array(':goodsid'=>$id));
//            if(!empty($productprice)){
//                $goods['productprice']=$productprice['productprice'];
//            }
        }

        if( $seckillinfo && $seckillinfo['status']==0){
//            $minprice = $maxprice = $goods['marketprice'] = $seckillinfo['price'];  //秒杀价格显示问题 lgt
            if(count($seckillinfo['options'])>0 && !empty($options)){
                foreach($options as &$option){
                    foreach($seckillinfo['options'] as $so){
                        if($option['id']==$so['optionid']){
                            $option['marketprice'] = $so['price'];
                        }
                    }
                }
                unset($option);
            }
        }

        $goods['minprice'] = number_format( $minprice,2);
        $goods['maxprice'] =number_format(  $maxprice,2);

        $buttonFixedImageSetting = m('common')->getGoodsBottomFixedImageSetting();

        // 主商城应用并且开启了底部信息展示
        if (empty($goods['merchid']) && $buttonFixedImageSetting['shopStatus']) {
            $goods['bottomFixedImageUrls'] = empty($buttonFixedImageSetting['urls']) ? array() : $buttonFixedImageSetting['urls'];
        } else if ($goods['merchid'] != 0 && $buttonFixedImageSetting['merchStatus']) {
            $goods['bottomFixedImageUrls'] = empty($buttonFixedImageSetting['urls']) ? array() : $buttonFixedImageSetting['urls'];
        } else {
            $goods['bottomFixedImageUrls'] = array();
        }

        app_json(array('goods'=>$goods));
    }
    /**
     * 计算出此商品的佣金
     * @param type $goodsid
     * @return type
     */
    public function getCommission($goods,$level,$set)
    {
        global $_W;
        $commission = 0;
        if($level =='false'){
            return $commission;
        }
        if ($goods['hascommission'] == 1) {
            $price = $goods['maxprice'];
            $levelid = 'default';

            if ($level) {
                $levelid = 'level' . $level['id'];
            }

            $goods_commission = !empty($goods['commission']) ? json_decode($goods['commission'], true) : array();

            if ($goods_commission['type'] == 0) {
                $commission = $set['level'] >= 1 ? ($goods['commission1_rate'] > 0 ? ($goods['commission1_rate'] * $goods['marketprice'] / 100) : $goods['commission1_pay']) : 0;
            } else {
                $price_all = array();
                foreach ($goods_commission[$levelid] as $key => $value) {
                    foreach ($value as $k => $v) {
                        if (strexists($v, '%')) {
                            array_push($price_all, floatval(str_replace('%', '', $v) / 100) * $price);
                            continue;
                        }
                        array_push($price_all, $v);
                    }
                }
                $commission = max($price_all);
            }
        } else {
            if (!empty($level)) {
                $commission = $set['level'] >= 1 ? round($level['commission1'] * $goods['marketprice'] / 100, 2) : 0;
            } else {
                $commission = $set['level'] >= 1 ? round($set['commission1'] * $goods['marketprice'] / 100, 2) : 0;
            }
        }

        return $commission;
    }

    function getLevel($openid)
    {
        global $_W;
        $level = 'false';
        if (empty($openid)) {
            return $level;
        }
        $member = m('member')->getMember($openid);
        if (empty($member['isagent']) || $member['status']==0 || $member['agentblack'] ==1) {

            return $level;
        }
        $level = pdo_fetch('select * from ' . tablename('ewei_shop_commission_level') . ' where uniacid=:uniacid and id=:id limit 1', array(':uniacid' => $_W['uniacid'], ':id' => $member['agentlevel']));

        return $level;
    }
    public function get_comments() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $percent = 100;
        $params = array(':goodsid'=>$id,':uniacid'=>$_W['uniacid']);
        $count = array(
            "all"=>pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and level>=0 and deleted=0 and checked=0 and uniacid=:uniacid",$params),
            "good"=>pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and level>=5 and deleted=0 and checked=0 and uniacid=:uniacid",$params),
            "normal"=>pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and level>=2 and level<=4 and deleted=0 and checked=0 and uniacid=:uniacid",$params),
            "bad"=>pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and level<=1 and deleted=0 and checked=0 and uniacid=:uniacid",$params),
            "pic"=>pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and ifnull(images,'a:0:{}')<>'a:0:{}' and deleted=0 and checked=0 and uniacid=:uniacid",$params)
        ) ;
        $list = array();
        if($count['all']>0){
            $percent = intval( $count['good'] / (empty($count['all'])?1:$count['all']) * 100);
            $list = pdo_fetchall('select nickname,level,content,images,createtime from '.tablename('ewei_shop_order_comment')." where goodsid=:goodsid and deleted=0 and checked=0 and uniacid=:uniacid order by istop desc, createtime desc, id desc limit 2",array(':goodsid'=>$id,':uniacid'=>$_W['uniacid']));
            foreach($list as &$row){
                $row['createtime'] = date('Y-m-d H:i',$row['createtime']);
                $row['images'] = set_medias(iunserializer($row['images']));
                $row['nickname'] = cut_str($row['nickname'], 1, 0).'**'.cut_str($row['nickname'], 1, -1);
            }
            unset($row);
        }
        app_json(array('count'=>$count, 'percent'=>$percent, 'list'=>$list));
    }

    public function get_comment_list() {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $level = trim($_GPC['level']);
        $params = array(':goodsid'=>$id,':uniacid'=>$_W['uniacid']);
        $pindex = max(1, intval($_GPC['page']));
        $page = intval($_GPC['page']);
        $psize = 10;
        $condition = "";
        if($level=='good'){
            $condition=" and level=5";
        } else if($level=='normal'){
            $condition=" and level>=2 and level<=4";
        }else if($level=='bad'){
            $condition=" and level<=1";
        }else if($level=='pic'){
            $condition=" and ifnull(images,'a:0:{}')<>'a:0:{}'";
        }
        $list = pdo_fetchall("select * from ".tablename('ewei_shop_order_comment')." "
            . "  where goodsid=:goodsid and uniacid=:uniacid and deleted=0 and checked=0 $condition order by istop desc, createtime desc, id desc LIMIT " . ($pindex - 1) * $psize . ',' . $psize, $params);
        foreach($list as &$row){
            $row['headimgurl'] = tomedia($row['headimgurl']);
            $row['createtime'] = date('Y-m-d H:i',$row['createtime']);
            $row['images'] = set_medias(iunserializer($row['images']));
            $row['reply_images'] = set_medias(iunserializer($row['reply_images']));
            $row['append_images'] = set_medias(iunserializer($row['append_images']));
            $row['append_reply_images'] = set_medias(iunserializer($row['append_reply_images']));
            $row['nickname'] = cut_str($row['nickname'], 1, 0).'**'.cut_str($row['nickname'], 1, -1);
        }
        unset($row);
        $total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . " where goodsid=:goodsid  and uniacid=:uniacid and deleted=0 and checked=0 {$condition}", $params);
        app_json(array('list'=>$list,'total'=>$total,'pagesize'=>$psize,'page'=>$page+1));
    }

    /**
     * 商品简介
     */
    public function get_content() {
        global $_W,$_GPC;
        $uniacid = $_W['uniacid'];
        $id = intval($_GPC['id']);

        //商品
        $goods = pdo_fetch("select content from " . tablename('ewei_shop_goods') . " where id=:id and uniacid=:uniacid limit 1", array(':id' => $id, ':uniacid' => $_W['uniacid']));
        app_json(array('content'=>base64_encode($goods['content'])));
    }

    /**
     * 获取规格选择
     */
    public function get_picker() {
        global $_W, $_GPC;

        $id = intval($_GPC['id']);
        $openid = $_W['openid'];
        $member = m('member')->getMember($openid, true);

        if(empty($id)){
            app_error(AppError::$ParamsError);
        }

        $seckillinfo = false;
        $seckill  = p('seckill');
        if( $seckill){
            $time = time();
            $seckillinfo = $seckill->getSeckill($id);

            if(!empty($seckillinfo)){
                if($time >= $seckillinfo['starttime'] && $time<$seckillinfo['endtime']){
                    $seckillinfo['status'] = 0;
                }elseif( $time < $seckillinfo['starttime'] ){
                    $seckillinfo['status'] = 1;
                }else {
                    $seckillinfo['status'] = -1;
                }
            }
        }

        //商品
        $goods = pdo_fetch('select id,thumb,title,marketprice,total,maxbuy,minbuy,unit,isdiscount,isdiscount_time,isdiscount_discounts,hasoption,showtotal,diyformid,diyformtype,diyfields, `type`, isverify, maxprice, minprice, merchsale,hascommission,nocommission,commission,commission1_rate,marketprice,commission1_pay,preselltimestart,presellovertime,presellover,ispresell,preselltimeend,presellprice from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if(empty($goods)){
            app_error(AppError::$GoodsNotFound);
        }
        $goods = set_medias($goods,'thumb');


        /*
        if($goods['isdiscount'] && $goods['isdiscount_time']>=time()){
            //有促销
            $isdiscount = true;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'],true);
            $member = m('member')->getMember($openid);
            $levelid = $member['level'];
            $key = empty($levelid)?'default':'level'.$levelid;
        } else {
            $isdiscount = false;
        }
        */



        $specs =array();
        $options = array();
        if (!empty($goods) && $goods['hasoption']) {
            $specs = pdo_fetchall('select * from ' . tablename('ewei_shop_goods_spec') . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
            foreach($specs as &$spec) {
                $spec['items'] = pdo_fetchall('select *  from '.tablename('ewei_shop_goods_spec_item')." where specid=:specid and `show`=1 order by displayorder asc",array(':specid'=>$spec['id']));
            }
            unset($spec);
            $options = pdo_fetchall('select *  from ' . tablename('ewei_shop_goods_option') . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
        }

        //价格显示
        $minprice = $goods['minprice'];
        $maxprice = $goods['maxprice'] ;

        if($goods['isdiscount'] && $goods['isdiscount_time']>=time()){
            $goods['oldmaxprice'] = $maxprice;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'],true);
            $prices = array();

            if (!isset($isdiscount_discounts['type']) || empty($isdiscount_discounts['type'])) {
                //统一促销
                $level = m('member')->getLevel($openid);
                $prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
                $prices[] = $prices_array['price'];
            } else {
                //详细促销
                $goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid, $options);
                $prices = $goods_discounts['prices'];
                $options = $goods_discounts['options'];
            }

            $minprice = min($prices);
            $maxprice = max($prices);
            $goods['minprice'] = (float)$minprice; $goods['maxprice'] =(float)$maxprice;
        }

        if( $seckillinfo && $seckillinfo['status']==0){
            $minprice = $maxprice = $goods['marketprice'] = $seckillinfo['price'];
            if(count($seckillinfo['options'])>0 && !empty($options)){
                foreach($options as &$option){
                    foreach($seckillinfo['options'] as $so){
                        if($option['id']==$so['optionid']){
                            $option['marketprice'] = $so['price'];
                        }
                    }
                }
                unset($option);
            }
        } else{
            $minprice = $goods['minprice'];
            $maxprice = $goods['maxprice'] ;
        }

        if($goods['ispresell']>0 && ($goods['preselltimeend'] == 0 || $goods['preselltimeend'] > time())){
            //预售商品价格处理 lgt
            $goods['thistime'] =time();
            if(!empty($options)){
                $presell = pdo_fetch("select min(presellprice) as minprice,max(presellprice) as maxprice from ".tablename('ewei_shop_goods_option')." where goodsid = ".$id);
                $minprice = $presell['minprice'];
                $maxprice = $presell['maxprice'];
            }
            $goods['presellstartstatus'] = true;
            $goods['presellendstatus'] = true;
            if(!empty($goods['preselltimestart'])){
                if($goods['preselltimestart'] > time()){
                    $goods['presellstartstatus'] = false;
                    $goods['presellstatustitle'] = '预售未开始';
                }
            }
            if(!empty($goods['preselltimeend'])){
                if($goods['preselltimeend'] < time()){
                    $goods['presellendstatus'] = false;
                    $goods['presellstatustitle'] = '预售已结束';
                }
            }
        }

        $goods['minprice'] = number_format( $minprice,2); $goods['maxprice'] =number_format(  $maxprice,2);

        //        获取不同规格的不同佣金
        $clevel = $this->getLevel($_W['openid']);
        $set = array();
        if(p('commission')){
            $set = p('commission')->getSet();
        }
        if(p('seckill')){
            if(p('seckill')->getSeckill($goods['id'])){
                //                    秒杀
                $seecommission = 0;
            }
        }
        if($goods['bargain'] > 0) {
            //        bargain 砍价
            $seecommission = 0;
        }else{
            if($goods['nocommission'] ==1){
                $seecommission = 0;
            }else if($goods['hascommission'] == 1 && $goods['nocommission'] ==0 && $member['isagent'] && $member['isagent']){
                $price = $goods['maxprice'];
                $levelid = 'default';

                if($clevel == 'false') {
                    $seecommission = 0;
                }else{
                    if($clevel) {
                        $levelid = 'level' . $clevel['id'];
                    }
                    $goods_commission = !empty($goods['commission']) ? json_decode($goods['commission'], true) : array();
                    if($goods_commission['type'] == 0) {
                        $seecommission = $set['level'] >= 1 ? ($goods['commission1_rate'] > 0 ? ($goods['commission1_rate'] * $goods['marketprice'] / 100) : $goods['commission1_pay']) : 0;
                        if(is_array($options)){
                            foreach ($options as $k => $v) {
                                $options[$k]['seecommission'] = $seecommission;
                            }
                        }
                    } else {
                        if(is_array($options)) {
                            //获取每个规格的佣金
                            foreach ($goods_commission[$levelid] as $key => $value) {
                                foreach ($options as $k => $v) {
                                    if(('option' . $v['id']) == $key) {
                                        if(strexists($value[0], '%')) {
                                            $options[$k]['seecommission'] = (floatval(str_replace('%', '', $value[0]) / 100) * $v['marketprice']);

                                            continue;
                                        } else {
                                            $options[$k]['seecommission'] = $value[0];

                                            continue;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }elseif($goods['hasoption'] ==1&&$goods['hascommission'] == 0 && $goods['nocommission'] ==0 && $member['isagent'] && $member['isagent']){
                foreach($options as $ke=>$vl){
                    if ($clevel!='false' && $clevel) {
                        $options[$ke]['seecommission'] = $set['level'] >= 1 ? round($clevel['commission1'] * $vl['marketprice'] / 100, 2) : 0;
                    } else {
                        $options[$ke]['seecommission'] = $set['level'] >= 1 ? round($set['commission1'] * $vl['marketprice'] / 100, 2) : 0;
                    }
                }
            }else{
                if ($clevel!='false' && $clevel) {
                    $seecommission = $set['level'] >= 1 ? round($clevel['commission1'] * $goods['marketprice'] / 100, 2) : 0;
                } else {
                    $seecommission = $set['level'] >= 1 ? round($set['commission1'] * $goods['marketprice'] / 100, 2) : 0;
                }
            }
        }


        $goods['cansee'] = $set['cansee'];
        if(!p('commission')) {
            $goods['cansee'] = 0;
        }
        $goods['seetitle'] = $set['seetitle'];

        //自定义表单
        $diyform_plugin = p('diyform');
        if($diyform_plugin){
            $fields = false;
            if($goods['diyformtype'] == 1){
                //模板
                if(!empty($goods['diyformid'])){
                    $diyformid = $goods['diyformid'];
                    $formInfo = $diyform_plugin->getDiyformInfo($diyformid);
                    $fields = $formInfo['fields'];
                }
            } else if($goods['diyformtype'] == 2){
                //自定义
                $diyformid = 0;
                $fields = iunserializer($goods['diyfields']);
                if(empty($fields)){
                    $fields = false;
                }
            }

            if(!empty($fields)){
                $inPicker = true;

                $f_data = $diyform_plugin->getLastData(3, 0, $diyformid, $id, $fields, $member);

                $flag = 0;
                if (!empty($f_data) && is_array($f_data)) {
                    foreach ($f_data as $k => $v) {
                        if (!empty($v)) {
                            $flag = 1;
                            break;
                        }
                    }
                }
                if (empty($flag)) {
                    $f_data = $diyform_plugin->getLastCartData($id);
                }

            }
        }
        if (!empty($specs))
        {
            foreach ($specs as $key => $value)
            {
                foreach ($specs[$key]['items'] as $k=>&$v)
                {
                    $v['thumb'] = tomedia($v['thumb']);
                }
                unset($v);
            }
        }

        //是否可以加入购物车
        $goods['canAddCart'] = 1;
        if ($goods['isverify'] == 2 || $goods['type'] == 2 || $goods['type'] == 3) {
            $goods['canAddCart'] = 0;
        }
        //赠品
        $sale_plugin = com('sale');
        $giftid = 0;
        $gifttitle = '';
        if($sale_plugin){
            $isgift = 0;
            $gifts = array();
            $giftgoods = array();
            $gifts = pdo_fetchall("select id,goodsid,giftgoodsid,thumb,title from ".tablename('ewei_shop_gift')." where uniacid = ".$_W['uniacid']." and activity = 2 and status = 1 and starttime <= ".time()." and endtime >= ".time()."  ");
            foreach($gifts as $key => &$value){
                $gid = explode(",",$value['goodsid']);
                foreach ($gid as $ke => $val){
                    if($val==$id){
                        $giftgoods = explode(",",$value['giftgoodsid']);
                        foreach($giftgoods as $k => $val){
                            $giftdata =  pdo_fetch("select id,title,thumb,marketprice,total from ".tablename('ewei_shop_goods')." where uniacid = ".$_W['uniacid']." and deleted = 0 and total>0  and status = 2 and id = ".$val." ");
                           if(!empty($giftdata)){
                               $isgift = 1;
                               $gifts[$key]['gift'][$k] = $giftdata;
                               $gifts[$key]['gift'][$k]['thumb'] = tomedia( $gifts[$key]['gift'][$k]['thumb']);
                               $gifttitle = !empty($value['gift'][$k]['title']) ? $value['gift'][$k]['title'] : '赠品';
                           }
                        }
                    }
                }
                if(empty($value['gift'])){
                    unset($gifts[$key]);
                }
            }
            if($isgift){
                $giftid = $gifts[0]['id'];
                $giftinfo = $gifts;
            }
        }
        $goods['giftid'] = $giftid;
        $goods['giftinfo'] = $giftinfo;
        $goods['gifttitle'] = $gifttitle;
        $goods['isgift'] = $isgift;

        unset($goods['diyformid'], $goods['diyformtype'], $goods['diyfields']);

        if(!empty($options) && is_array($options)){
            foreach ($options as $index=>&$option){
                $option_specs = $option['specs'];
                if(!empty($option_specs)){
                    $option_specs_arr = explode("_", $option_specs);
                    array_multisort($option_specs_arr, SORT_ASC);
                    $option['specs'] = implode("_", $option_specs_arr);
                }
            }
        }
        unset($option);
        $appDatas =array(
            'fields'=>array(),
            'f_data'=>array(),
        );
        if ($diyform_plugin){
            $appDatas = $diyform_plugin->wxApp($fields, $f_data, $this->member);
        }
        app_json(array(
            'goods' => $goods,
            'seckillinfo'=>$seckillinfo,
            'specs' => $specs,
            'options' => $options,
            'diyform'=>array(
                'fields'=>$appDatas['fields'],
                'lastdata'=>$appDatas['f_data']
            )
        ));
    }

    /**
     * @param $goods
     * @return array|int
     */
    protected function getGoodsDispatchPrice($goods,$is_seckill=false) {
        if (!empty($goods['issendfree']) && empty($is_seckill) ) {
            //包邮
            return 0;
        }

        if ($goods['type'] == 2 || $goods['type'] == 3 || $goods['type'] == 20) {
            //虚拟物品或虚拟卡密
            return 0;
        }
        if ($goods['dispatchtype'] == 1) {
            //统一运费
            return $goods['dispatchprice'];
        } else {
            //运费模板

            if (empty($goods['dispatchid'])) {
                //默认快递
                $dispatch = m('dispatch')->getDefaultDispatch($goods['merchid']);
            } else {
                $dispatch = m('dispatch')->getOneDispatch($goods['dispatchid']);
            }

            if (empty($dispatch)) {
                //最新的一条快递信息
                $dispatch = m('dispatch')->getNewDispatch($goods['merchid']);
            }

            $areas = iunserializer($dispatch['areas']);
            if (!empty($areas) && is_array($areas))
            {
                $firstprice = array();
                foreach ($areas as $val){
                    //判断计费方式
                    if(empty($dispatch['calculatetype'])){
                        $firstprice[] = $val['firstprice'];
                    }else{
                        $firstprice[] = $val['firstnumprice'];
                    }
                }
                array_push($firstprice,m('dispatch')->getDispatchPrice(1, $dispatch));
                $ret = array(
                    'min' => round(min($firstprice),2),
                    'max' => round(max($firstprice),2)
                );
            }
            else
            {
                $ret = m('dispatch')->getDispatchPrice(1, $dispatch);
            }
            return $ret;
        }
    }

    /**
     * 获取分类
     */
    public function get_category() {
        global $_W,$_GPC;
        $allcategory = m('shop')->getCategory();
        $catlevel = intval($_W['shopset']['category']['level']);
        $opencategory = true; //是否自己商品不同步分类
        $plugin_commission = p('commission');
        if ($plugin_commission && intval($_W['shopset']['commission']['level']) > 0) {
            $mid = intval($_GPC['mid']);
            if (!empty($mid)) {
                $shop = p('commission')->getShop($mid);
                if (empty($shop['selectcategory'])) {
                    $opencategory = false;
                }
            }
        }
        app_json(array(
            'allcategory'=>$allcategory,
            'catlevel'=>$catlevel,
            'opencategory'=>$opencategory
        ));

    }

    /**
     * 获取当前商品及当前用户组可领取的免费优惠券
     * @param $goodid
     * @return array
     */
    protected function getCouponsbygood($goodid){
        global $_W, $_GPC;

        //多商户
        $merchdata = $this->merchData();
        extract($merchdata);
        $time = time();

        // 读取 优惠券
        $time = time();

        $param = array();
        $param[':uniacid'] = $_W['uniacid'];

        $sql = "select id,timelimit,coupontype,timedays,timestart,timeend,thumb,couponname,enough,backtype,deduct,discount,backmoney,backcredit,backredpack,bgcolor,thumb,credit,money,getmax,merchid,total as t,islimitlevel,limitmemberlevels,limitagentlevels,limitpartnerlevels,limitaagentlevels,limitgoodcatetype,limitgoodcateids,limitgoodtype,limitgoodids,tagtitle,settitlecolor,titlecolor from " . tablename('ewei_shop_coupon') . " c ";
        $sql.=" where uniacid=:uniacid and money=0 and credit = 0 and coupontype=0";
        if ($is_openmerch == 0) {
            $sql .= ' and merchid=0';
        }else {
            if (!empty($_GPC['merchid'])) {
                $sql .= ' and merchid=:merchid';
                $param[':merchid'] = intval($_GPC['merchid']);
            }else{
                $sql .= ' and merchid=0';
            }
        }

        //分销商限制
        $hascommission = false;
        $plugin_com = p('commission');
        if ($plugin_com) {
            $plugin_com_set = $plugin_com->getSet();
            $hascommission = !empty($plugin_com_set['level']);
            if(empty($plugin_com_set['level']))
            {
                $sql .= ' and ( limitagentlevels = "" or  limitagentlevels is null )';
            }
        } else {
            $sql .= ' and ( limitagentlevels = "" or  limitagentlevels is null )';
        }

        //股东限制
        $hasglobonus = false;
        $plugin_globonus = p('globonus');
        if ($plugin_globonus) {
            $plugin_globonus_set = $plugin_globonus->getSet();
            $hasglobonus = !empty($plugin_globonus_set['open']);
            if(empty($plugin_globonus_set['open'])) {
                $sql .= ' and ( limitpartnerlevels = ""  or  limitpartnerlevels is null )';
            }
        } else {
            $sql .= ' and ( limitpartnerlevels = ""  or  limitpartnerlevels is null )';
        }

        //区域代理限制
        $hasabonus = false;
        $plugin_abonus = p('abonus');
        if ($plugin_abonus) {
            $plugin_abonus_set = $plugin_abonus->getSet();
            $hasabonus = !empty($plugin_abonus_set['open']);
            if(empty($plugin_abonus_set['open'])) {
                $sql .= ' and ( limitaagentlevels = "" or  limitaagentlevels is null )';
            }
        } else {
            $sql .= ' and ( limitaagentlevels = "" or  limitaagentlevels is null )';
        }

        $sql.=" and gettype=1 and (total=-1 or total>0) and ( timelimit = 0 or  (timelimit=1 and timeend>{$time}))";
        $sql.=" order by displayorder desc, id desc  ";

        $list = set_medias(pdo_fetchall($sql, $param), 'thumb');
        if(empty($list)) {
            $list=array();
        }

        if(!empty($goodid)) {
            $goodparam[':uniacid'] = $_W['uniacid'];
            $goodparam[':id'] = $goodid;
            $sql = "select id,cates,marketprice,merchid   from " . tablename('ewei_shop_goods') ;
            $sql.=" where uniacid=:uniacid and id =:id order by id desc LIMIT 1 "; //类型+最低消费+示使用
            $good = pdo_fetch($sql, $goodparam);
        }

        $cates = explode(',',$good['cates']);

        if (!empty($list)) {
            foreach ($list as $key =>&$row) {
                $row = com('coupon')->setCoupon($row, time());
                $row['thumb'] = tomedia($row['thumb']);
                $row['timestr'] = "永久有效";
                if (empty($row['timelimit'])) {
                    if (!empty($row['timedays'])) {
                        $row['timestr'] = "自领取日后".$row['timedays']."天有效";
                    }
                } else {
                    if ($row['timestart'] >= $time) {
                        $row['timestr'] = '有效期至:'.date('Y-m-d', $row['timestart']) . '-' . date('Y-m-d', $row['timeend']);
                    } else {
                        $row['timestr'] = '有效期至:'.date('Y-m-d', $row['timeend']);
                    }
                }

                if ($row['backtype'] == 0) {
                    $row['backstr'] = '立减';
                    $row['backmoney'] = (float)$row['deduct'];
                    $row['backpre'] = true;

                    if($row['enough']=='0') {
                        $row['color']='org ';
                    } else {
                        $row['color']='blue';
                    }
                } else if ($row['backtype'] == 1) {
                    $row['backstr'] = '折';
                    $row['backmoney'] = (float)$row['discount'];
                    $row['color']='red ';
                } else if ($row['backtype'] == 2) {
                    if($row['coupontype']=='0') {
                        $row['color']='red ';
                    } else {
                        $row['color']='pink ';
                    }
                    if ($row['backredpack'] > 0) {
                        $row['backstr'] = '返现';
                        $row['backmoney'] = (float)$row['backredpack'];
                        $row['backpre'] = true;
                    } else if ($row['backmoney'] > 0) {
                        $row['backstr'] = '返利';
                        $row['backmoney'] = (float)$row['backmoney'];
                        $row['backpre'] = true;
                    } else if (!empty($row['backcredit'])) {
                        $row['backstr'] = '返积分';
                        $row['backmoney'] = (float)$row['backcredit'];
                    }
                }

                //分类限制
                $limitmemberlevels =explode(",", $row['limitmemberlevels']);
                $limitagentlevels =explode(",", $row['limitagentlevels']);
                $limitpartnerlevels=explode(",", $row['limitpartnerlevels']);
                $limitaagentlevels=explode(",", $row['limitaagentlevels']);

                $p= 0;
                if($row['islimitlevel'] ==1) {
                    $openid = trim($_W['openid']);
                    $member = m('member')->getMember($openid);
                    if(!empty($row['limitmemberlevels'])||$row['limitmemberlevels']=='0'){
                        //会员等级
                        $level1 = pdo_fetchall('select * from ' . tablename('ewei_shop_member_level') . ' where uniacid=:uniacid and  id in ('.$row['limitmemberlevels'].') ', array(':uniacid' => $_W['uniacid']));
                        if (in_array($member['level'],$limitmemberlevels)){
                            $p= 1;
                        }
                    };

                    if((!empty($row['limitagentlevels'])||$row['limitagentlevels']=='0')&&$hascommission) {
                        //分销商等级
                        $level2 = pdo_fetchall('select * from ' . tablename('ewei_shop_commission_level') . ' where uniacid=:uniacid and id  in ('.$row['limitagentlevels'].') ', array(':uniacid' => $_W['uniacid']));
                        if($member['isagent']=='1'&&$member['status']=='1') {
                            if (in_array($member['agentlevel'],$limitagentlevels)){
                                $p= 1;
                            }
                        }
                    }

                    if((!empty($row['limitpartnerlevels'])||$row['limitpartnerlevels']=='0')&&$hasglobonus) {
                        //股东等级
                        $level3 = pdo_fetchall('select * from ' . tablename('ewei_shop_globonus_level') . ' where uniacid=:uniacid and  id in('.$row['limitpartnerlevels'].') ', array(':uniacid' => $_W['uniacid']));
                        if($member['ispartner']=='1'&&$member['partnerstatus']=='1') {
                            if (in_array($member['partnerlevel'],$limitpartnerlevels)){
                                $p= 1;
                            }
                        }
                    }
                    if((!empty($row['limitaagentlevels'])||$row['limitaagentlevels']=='0')&&$hasabonus) {
                        //区域代理
                        $level4 = pdo_fetchall('select * from ' . tablename('ewei_shop_abonus_level') . ' where uniacid=:uniacid and  id in ('.$row['limitaagentlevels'].') ', array(':uniacid' => $_W['uniacid']));
                        if($member['isaagent']=='1'&&$member['aagentstatus']=='1') {
                            if (in_array($member['aagentlevel'],$limitaagentlevels)){
                                $p= 1;
                            }
                        }
                    }
                }else {
                    $p= 1;
                }

                if($p== 1) {
                    $p=0;
                    $limitcateids =explode(',',$row['limitgoodcateids']);
                    $limitgoodids =explode(',',$row['limitgoodids']);
                    if($row['limitgoodcatetype']==0&&$row['limitgoodtype']==0) {
                        $p= 1;
                    }
                    if($row['limitgoodcatetype']==1) {
                        $result = array_intersect($cates,$limitcateids);
                        if(count($result)>0) {
                            $p= 1;
                        }
                    }
                    if($row['limitgoodtype']==1) {
                        $isin = in_array($good['id'],$limitgoodids);
                        if($isin){
                            $p= 1;
                        }
                    }
                    //判断当前优惠券是否有可以生效的商品;
                    if($p==0) {
                        unset($list[$key]);
                    }
                }else {
                    unset($list[$key]);
                }
            }
            unset($row);
        }
        return array_values($list);
    }

    //商品详情页领取可用优惠券
    public function pay_coupon(){
        global $_W, $_GPC;

        $openid = $_W['openid'];
        $id = intval($_GPC['id']);
        $coupon = pdo_fetch('select * from ' . tablename('ewei_shop_coupon') . ' where id=:id and uniacid=:uniacid  limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
        $coupon = com('coupon')->setCoupon($coupon, time());
        //无法从领券中心领取
        if (empty($coupon['gettype'])) {
            app_error(AppError::$CouponBuyError, '无法'.$coupon['gettypestr']);
        }

        if ($coupon['total'] != -1) {
            if ($coupon['total'] <= 0) {
                app_error(AppError::$CouponBuyError, '优惠券数量不足');
            }
        }
        if (!$coupon['canget']) {
            app_error(AppError::$CouponBuyError, "您已超出{$coupon['gettypestr']}次数限制");
        }
        if($coupon['money'] > 0|| $coupon['credit']>0) {
            app_error(AppError::$CouponBuyError, '此优惠券需要前往领卷中心兑换');
        }

        $logno = m('common')->createNO('coupon_log', 'logno', 'CC');

        //生成日志
        $log = array(
            'uniacid' => $_W['uniacid'],
            'merchid' => $coupon['merchid'],
            'openid' => $openid,
            'logno' => $logno,
            'couponid' => $id,
            'status' => 0,
            'paystatus' => -1 ,
            'creditstatus' => -1 ,
            'createtime' => time(),
            'getfrom'=>1
        );
        pdo_insert('ewei_shop_coupon_log', $log);

        $result = com('coupon')->payResult($log['logno']);
        if(is_error($result)){
            app_error(AppError::$CouponBuyError, '领取失败('. $result['errno']. ') '. $result['message']);
        }

        app_json(array(
            //'url'=>$result['url'],
            'dataid'=>$result['dataid'],
            'coupontype'=>$result['coupontype']
        ));
    }

    //多商户
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
}

