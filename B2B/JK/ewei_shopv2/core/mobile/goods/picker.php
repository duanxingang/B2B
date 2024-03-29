<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Picker_EweiShopV2Page extends MobileLoginPage {

    function main()
    {
        global $_W, $_GPC;
        $_W['openid'] = $_SESSION['wechat_openid'];
        $id = intval($_GPC['id']);
        $action = trim($_GPC['action']);
        $rank = intval($_SESSION[$id . '_rank']);
        $log_id = intval($_SESSION[$id . '_log_id']);
        $join_id = intval($_SESSION[$id . '_join_id']);

        $cremind = false;
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

        /* 直播间商品 处理Step.1 */
        $liveid = intval($_GPC['liveid']);
        if(!empty($liveid)){
            $isliving=false;
            if(p('live')){
                $isliving = p('live')->isLiving($liveid);
            }
            if(!$isliving){
                $liveid = 0;
            }
        }


        //商品
        $goods = pdo_fetch('select id,discounts,thumb,title,marketprice,total,maxbuy,minbuy,unit,hasoption,showtotal,diyformid,diyformtype,diyfields,isdiscount,presellprice,isdiscount_time,isdiscount_discounts,hascommission,nocommission,commission,commission1_rate,marketprice,commission1_pay,needfollow, followtip, followurl, `type`, isverify, maxprice, minprice, merchsale,ispresell,preselltimeend,unite_total,
                threen,preselltimestart,presellovertime,presellover,islive,liveprice,minliveprice,maxliveprice
                from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if (empty($goods)) {
            show_json(0);
        }

        //商品参数
        $goods['gg'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $id,'uniacid' => $_W['uniacid'],'title'=>'规格'), 'value');
        $goods['ph'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $id,'uniacid' => $_W['uniacid'],'title'=>'批号'), 'value');
        $goods['xq'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $id,'uniacid' => $_W['uniacid'],'title'=>'有效期'), 'value');
        $goods['cq'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $id,'uniacid' => $_W['uniacid'],'title'=>'生产日期'), 'value');
        $goods['cj'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $id,'uniacid' => $_W['uniacid'],'title'=>'生产厂家'), 'value');

        $threenprice = json_decode($goods['threen'],1);
        $goods['thistime'] = time();
        $goods = set_medias($goods, 'thumb');


        /* 直播间商品 处理Step.2 */
        if(!empty($liveid)){
            $islive =false;
            if(p('live')){
                $islive = p('live')->getLivePrice($goods, $liveid);
            }
            if($islive){
                $goods['minprice'] = $islive['minprice'];
                $goods['maxprice'] = $islive['maxprice'];
            }
        }


        $openid = $_W['openid'];

        if (is_weixin()) {
            $follow = m("user")->followed($openid);
            if (!empty($goods['needfollow']) && !$follow) {
                $followtip = empty($goods['followtip']) ? "如果您想要购买此商品，需要您关注我们的公众号，点击【确定】关注后再来购买吧~" : $goods['followtip'];
                $followurl = empty($goods['followurl']) ? $_W['shopset']['share']['followurl'] : $goods['followurl'];
                show_json(2, array('followtip' => $followtip, 'followurl' => $followurl));
            }
        }


        $openid =$_W['openid'];

        $member = m('member')->getMember($openid);
    
        //  验证是否登录
        if(empty($openid)){
            $sendtime = $_SESSION['verifycodesendtime'];
            if(empty($sendtime) || $sendtime+60<time()){
                $endtime = 0;
            }else{
                $endtime = 60 - (time() - $sendtime);
            }
            show_json(4, array(
                'endtime'=>$endtime,
                'imgcode'=>$_W['shopset']['wap']['smsimgcode']
            ));
        }

        //  验证手机号
        if(!empty($_W['shopset']['wap']['open']) && !empty($_W['shopset']['wap']['mustbind']) && empty($member['mobileverify'])){
            $sendtime = $_SESSION['verifycodesendtime'];
            if(empty($sendtime) || $sendtime+60<time()){
                $endtime = 0;
            }else{
                $endtime = 60 - (time() - $sendtime);
            }
            show_json(3, array(
                'endtime'=>$endtime,
                'imgcode'=>$_W['shopset']['wap']['smsimgcode']
            ));
        }
        //预售
        if($goods['ispresell'] > 0){
            $times = $goods['presellovertime'] * 60 * 60 * 24 + $goods['preselltimeend'];
            if(!($goods['presellover']>0 && $times <= time())){
                if($goods['preselltimestart'] > 0 && $goods['preselltimestart'] > time()){
                    show_json(5,'预售未开始');
                }
                if($goods['preselltimeend'] > 0 && $goods['preselltimeend'] < time()){
                    show_json(5,'预售已结束');
                }
            }

            //预售结束转为正常销售
            /*$times = $goods['presellovertime'] * 60 * 60 * 24 + $goods['preselltimeend'];
            if($goods['presellover']>0 && $times <= time() && $goods['preselltimeend'] > 0 && $goods['preselltimeend'] < time()){

            }else{
                show_json(5,'预售已结束');
            }*/
        }


        if($goods['isdiscount'] && $goods['isdiscount_time']>=time()){
            //有促销
            $isdiscount = true;
            $isdiscount_discounts = json_decode($goods['isdiscount_discounts'],true);
            $levelid = $member['level'];
            $key = empty($levelid)?'default':'level'.$levelid;
        } else {
            $isdiscount = false;
        }

        //任务活动购买商品
        $task_goods_data = m('goods')->getTaskGoods($openid, $id, $rank, $log_id, $join_id);
        if (empty($task_goods_data['is_task_goods'])) {
            $is_task_goods = 0;
        } else {
            $is_task_goods = $task_goods_data['is_task_goods'];
            $is_task_goods_option = $task_goods_data['is_task_goods_option'];
            $task_goods = $task_goods_data['task_goods'];
        }

        $specs =false;
        $options = false;
        if (!empty($goods) && $goods['hasoption']) {
            $specs = pdo_fetchall('select * from ' . tablename('ewei_shop_goods_spec') . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
            foreach($specs as &$spec) {
                $spec['items'] = pdo_fetchall('select * from '.tablename('ewei_shop_goods_spec_item')." where specid=:specid and `show`=1 order by displayorder asc",array(':specid'=>$spec['id']));
            }
            unset($spec);
            $options = pdo_fetchall('select * from ' . tablename('ewei_shop_goods_option') . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
        }

        if (!empty($options) && !empty($goods['unite_total'])) {
            foreach($options as &$option){
                $option['stock'] = $goods['total'];
            }
            unset($option);
        }

        /* 直播间商品 处理Step.3 */
        if(!empty($liveid) && !empty($options)){
            // 重新获取直播商品规格价格
            //$options =array();
            if(p('live')){
                $options = p('live')->getLiveOptions($goods['id'], $liveid, $options);
            }
            $prices = array();
            foreach ($options as $option){
                $prices[] = price_format($option['marketprice']);
            }
            unset($option);

            $goods['minprice'] = min($prices);
            $goods['maxprice'] = max($prices);
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

        //价格显示
        if (!empty($is_task_goods)) {
            if ( isset($options) && count($options) > 0 && $goods['hasoption']) {
                $prices = array();
                foreach ($task_goods['spec'] as $k => $v) {
                    $prices[] = $v['marketprice'];
                }
                $minprice = min($prices);
                $maxprice = max($prices);

                foreach ($options as $k => $v) {
                    $option_id = $v['id'];
                    if (array_key_exists($option_id, $task_goods['spec'])) {
                        if($goods['ispresell']>0 && ($goods['preselltimeend'] == 0 || $goods['preselltimeend'] > time())){
                            $options[$k]['marketprice'] = $task_goods['spec'][$option_id]['presellprice'];
                        }else{
                            $options[$k]['marketprice'] = $task_goods['spec'][$option_id]['marketprice'];
                        }
                        $options[$k]['stock'] = $task_goods['spec'][$option_id]['total'];
                    }
                    $prices[] = $v['marketprice'];
                }

            } else {
                $minprice = $task_goods['marketprice'];
                $maxprice = $task_goods['marketprice'];
            }

        } else {
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
            }
        }

//        获取不同规格的不同佣金
        $clevel = $this->getcLevel($_W['openid']);
        $set = array();
        if(p('commission')) {
            $set = $this->getSet();
            $goods['cansee'] = $set['cansee'];
            $goods['seetitle'] = $set['seetitle'];
        }else{
            $goods['cansee'] = 0;
            $goods['seetitle'] = '';
        }
        if(p('seckill')){
            if(!p('seckill')->getSeckill($goods['id'])){
//                    秒杀


        if($goods['nocommission'] ==1){
            $seecommission = 0;
        }else if($goods['hascommission'] == 1 && $goods['nocommission'] ==0){

            $price = $goods['maxprice'];
            $levelid = 'default';
            if($clevel == 'false'){
                $seecommission = 0;
            }else {
                if($clevel) {
                    $levelid = 'level' . $clevel['id'];
                }
                $goods_commission = !empty($goods['commission']) ? json_decode($goods['commission'], true) : '';
                if($goods_commission['type'] == 0) {
                    $seecommission = $set['level'] >= 1 ? ($goods['commission1_rate'] > 0 ? ($goods['commission1_rate'] * $goods['marketprice'] / 100) : $goods['commission1_pay']) : 0;
                    if(is_array($options) && !empty($options)){
                        foreach ($options as $k => $v) {
                            $seecommission = $set['level'] >= 1 ? ($goods['commission1_rate'] > 0 ? ($goods['commission1_rate'] * $v['marketprice'] / 100) : $v['commission1_pay']) : 0;
                            $options[$k]['seecommission'] = $seecommission;
                        }
                    }
                } else {
                    //获取每个规格的佣金
                    if(is_array($options)) {
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
        }elseif($goods['hasoption'] ==1&&$goods['hascommission'] == 0 && $goods['nocommission'] ==0){
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
        }

        if($goods['ispresell']>0 && ($goods['preselltimeend'] == 0 || $goods['preselltimeend'] > time())){
            $presell = pdo_fetch("select min(presellprice) as minprice,max(presellprice) as maxprice from ".tablename('ewei_shop_goods_option')." where goodsid = ".$id);
            $minprice = $presell['minprice'];
            $maxprice = $presell['maxprice'];
        }


        $goods['minprice'] = number_format( $minprice,2); $goods['maxprice'] =number_format(  $maxprice,2);

        $diyformhtml = "";
        if ($action == 'cremind') {
            $cremind_plugin = p('cremind');
            $cremind_data = m('common')->getPluginset('cremind');
            if ($cremind_plugin && $cremind_data['remindopen']) {
                $cremind = true;
            }

            ob_start();
            include $this->template('cremind/formfields');
            $cremindformhtml = ob_get_contents();
            ob_clean();
        } else {
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
                    ob_start();
                    $inPicker = true;

                    $openid = $_W['openid'];
                    $member = m('member')->getMember($openid, true);
                    $f_data = $diyform_plugin->getLastData(3, 0, $diyformid, $id, $fields, $member);

                    $flag = 0;
                    if (!empty($f_data)) {
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

                    $area_set = m('util')->get_area_config_set();
                    $new_area = intval($area_set['new_area']);
                    $address_street = intval($area_set['address_street']);

                    include $this->template('diyform/formfields');
                    $diyformhtml = ob_get_contents();
                    ob_clean();
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
            }
        }
        //是否可以加入购物车
        $goods['canAddCart'] = true;
        if ($goods['isverify'] == 2 || $goods['type'] == 2 || $goods['type'] == 3 || $goods['type'] == 20 || !empty($goods['cannotrefund'])) {
            $goods['canAddCart'] = false;
        }


        if (p('task')){
            $task_id = intval($_SESSION[$id . '_task_id']);
            if (!empty($task_id)){
                $rewarded = pdo_fetchcolumn("SELECT `rewarded` FROM ".tablename('ewei_shop_task_extension_join')." WHERE id = :id AND uniacid = :uniacid",array(':id'=>$task_id,':uniacid'=>$_W['uniacid']));
                $taskGoodsInfo = unserialize($rewarded);
                $taskGoodsInfo = $taskGoodsInfo['goods'][$id];
                if (empty($taskGoodsInfo['option'])){
                    $goods['marketprice'] = $taskGoodsInfo['price'];
                }else{//有规格
                    foreach($options as $gk =>$gv){
                        if ($options[$gk]['id'] == $taskGoodsInfo){
                            $options[$gk]['marketprice'] = $taskGoodsInfo['price'];
                        }
                    }
                }
            }
        }

        //赠品
        $sale_plugin = com('sale');
        $giftid = 0;
        $goods['cangift']  = false;
        $gifttitle = '';
        if($sale_plugin){
            $giftinfo = array();
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
                            $giftdata = pdo_fetch("select id,title,thumb,marketprice,total from ".tablename('ewei_shop_goods')." where uniacid = ".$_W['uniacid']." and deleted = 0 and total > 0 and status = 2 and id = ".$val." ");
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
                if($_GPC['cangift']){
                    $goods['cangift'] = true;
                }
                $gifts = array_values($gifts);
                $giftid = $gifts[0]['id'];
                $giftinfo = $gifts;
            }
        }
        $goods['giftid'] = $giftid;
        $goods['giftinfo'] = $giftinfo;
        $goods['gifttitle'] = $gifttitle;
        $goods['gifttotal'] = count($goods['giftinfo']);

        //会员价格
        if($member['level'] > 0){
            $gl = 'level'.$member['level'].'_pay';
            $dis = json_decode($goods['discounts'],true);
            //会员等级对应的价格
            if(array_key_exists($gl, $dis) and !empty($dis[$gl])){
                $goods['minprice'] = $dis[$gl];
                $goods['maxprice'] = $dis[$gl];
                $goods['maxprice'] = $dis[$gl];
            }

        }else{
            //2019-7-10 修改
            $goods['minprice'] = $goods['marketprice'];
            $goods['maxprice'] = $goods['marketprice'];
        }


        show_json(1, array(
            'goods' => $goods,
            'seckillinfo'=>$seckillinfo,
            'specs' => $specs,
            'options' => $options,
            'diyformhtml'=>$diyformhtml,
            'cremind'=>$cremind,
            'cremindformhtml'=>$cremindformhtml
        ));
    }
//获取分销商等级
    function getcLevel($openid)
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

    function getSet()
    {
        $set = m('common')->getPluginset('commission');

        $set['texts'] = array(
            'agent' => empty($set['texts']['agent']) ? '分销商' : $set['texts']['agent'],
            'shop' => empty($set['texts']['shop']) ? '小店' : $set['texts']['shop'],
            'myshop' => empty($set['texts']['myshop']) ? '我的小店' : $set['texts']['myshop'],
            'center' => empty($set['texts']['center']) ? '分销中心' : $set['texts']['center'],
            'become' => empty($set['texts']['become']) ? '成为分销商' : $set['texts']['become'],
            'withdraw' => empty($set['texts']['withdraw']) ? '提现' : $set['texts']['withdraw'],
            'commission' => empty($set['texts']['commission']) ? '佣金' : $set['texts']['commission'],
            'commission1' => empty($set['texts']['commission1']) ? '分销佣金' : $set['texts']['commission1'],
            'commission_total' => empty($set['texts']['commission_total']) ? '累计佣金' : $set['texts']['commission_total'],
            'commission_ok' => empty($set['texts']['commission_ok']) ? '可提现佣金' : $set['texts']['commission_ok'],
            'commission_apply' => empty($set['texts']['commission_apply']) ? '已申请佣金' : $set['texts']['commission_apply'],
            'commission_check' => empty($set['texts']['commission_check']) ? '待打款佣金' : $set['texts']['commission_check'],
            'commission_lock' => empty($set['texts']['commission_lock']) ? '未结算佣金' : $set['texts']['commission_lock'],
            'commission_detail' => empty($set['texts']['commission_detail']) ? '提现明细' : ($set['texts']['commission_detail'] == '佣金明细' ? '提现明细' : $set['texts']['commission_detail']),
            'commission_pay' => empty($set['texts']['commission_pay']) ? '成功提现佣金' : $set['texts']['commission_pay'],
            'commission_wait' => empty($set['texts']['commission_wait']) ? '待收货佣金' : $set['texts']['commission_wait'],
            'commission_fail' => empty($set['texts']['commission_fail']) ? '无效佣金' : $set['texts']['commission_fail'],
            'commission_charge' => empty($set['texts']['commission_charge']) ? '扣除提现手续费' : $set['texts']['commission_charge'],
            'order' => empty($set['texts']['order']) ? '分销订单' : $set['texts']['order'],
            'c1' => empty($set['texts']['c1']) ? '一级' : $set['texts']['c1'],
            'c2' => empty($set['texts']['c2']) ? '二级' : $set['texts']['c2'],
            'c3' => empty($set['texts']['c3']) ? '三级' : $set['texts']['c3'],
            'mydown' => empty($set['texts']['mydown']) ? '我的下线' : $set['texts']['mydown'],
            'down' => empty($set['texts']['down']) ? '下线' : $set['texts']['down'],
            'up' => empty($set['texts']['up']) ? '推荐人' : $set['texts']['up'],
            'yuan' => empty($set['texts']['yuan']) ? '元' : $set['texts']['yuan'],
            'icode' => empty($set['texts']['icode']) ? '邀请码' : $set['texts']['icode']
        );
        return $set;
    }


    //控销 2019-6-28
    public function sell()
    {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        //商品
        $goods = pdo_fetch('select id,title,sell_cate,buygroups,buylevels from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
        if (empty($goods)) {
            show_json(0,'未找到商品！');
        }
        //用户
        $_W['openid'] = $_SESSION['wechat_openid'];
//        $member = m('member')->getMember($_W['openid']);
        $member = pdo_fetch("SELECT id,is_shen,isblack,bus_cate,level,groupid,is_tourist FROM ".tablename('ewei_shop_member')." WHERE openid = :openid and uniacid=:uniacid LIMIT 1", array(':uniacid' => $_W['uniacid'],':openid' => $_W['openid']));

        //业务等级
        if($member['is_tourist'] == 1){
            show_json(0,'您的账号没有购买权限');
        }

        //是否审核
        if($member['is_shen'] == 0){
            show_json(0,'您的账号还在审核中，请耐心等待~~');
        }
        //是黑名单
        if ($member['isblack'] == 1) {
            show_json(0,'您的账号已加入黑名单！');
        }


        //判断会员权限
        if ($goods['buylevels'] != '') {
            $buylevels = explode(',', $goods['buylevels']);
            if (!in_array($member['level'], $buylevels)) {
                show_json(0, '您的会员等级无法购买<br/>' . $goods['title'] . '!');
            }
        }
        if (empty($member['groupid'])){
            $groupid = array();
        }else{
            $groupid = explode(',',$member['groupid']);
        }
        //会员组权限
        if ($goods['buygroups'] != '') {
            if(empty($groupid)){
                $groupid[]=0;
            }
            $buygroups = explode(',', $goods['buygroups']);
            $intersect = array_intersect($groupid, $buygroups);
            if (empty($intersect)) {
                show_json(0, '您所在会员组无法购买<br/>' . $goods['title'] . '!');
            }
        }

        if(!empty($member['bus_cate']) && !empty($goods['sell_cate'])){
            $bus_cate = explode(',',$member['bus_cate']);
            if(!in_array($goods['sell_cate'],$bus_cate)){
                show_json(0,'抱歉，您的经营资质不能购买该商品！');
            }
        }
        show_json(1,'可以购买');
    }


}

