<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class List_EweiShopV2Page extends WebPage {

    function status0(){
        global $_W, $_GPC;
        $orderData = $this->main(0,__FUNCTION__);
    }

    function status1(){
        global $_W, $_GPC;
        $orderData = $this->main(1,__FUNCTION__);
    }

    function main($is_shen=1,$st=__FUNCTION__) {
        global $_W, $_GPC;
        $pindex = max(1, intval($_GPC['page']));
        $psize = 20;
//        $condition = " and dm.uniacid=:uniacid";
        $condition = " and dm.uniacid=:uniacid and dm.company!='' and dm.business_card!='' and dm.business_img!=''";
        $params = array(':uniacid' => $_W['uniacid']);
        if ($st == "main") {
            $st = '';
        } else {
            $st = ".".$st;
        }
        if($is_shen == 0){
            $condition.=' and dm.is_shen=:is_shen';
            $params[':is_shen'] = 0;
        }elseif ($is_shen == 1){
            $condition.=' and dm.is_shen=:is_shen';
            $params[':is_shen'] = 1;
        }else{
            $condition.=' and dm.is_shen=:is_shen';
            $params[':is_shen'] = 0;
        }
        if (!empty($_GPC['mid'])) {
            $condition.=' and dm.id=:mid';
            $params[':mid'] = intval($_GPC['mid']);
        }
        if (!empty($_GPC['realname'])) {
            $realname = trim($_GPC['realname']);
            $condition.=' and ( dm.realname like :realname or dm.nickname like :realname or dm.mobile like :realname or dm.id like :realname or dm.company like :realname or dm.contact_name like :realname or dm.contact_phone like :realname)';
            $params[':realname'] = '%' . $realname . '%';
        }
        if (empty($starttime) || empty($endtime)) {
            $starttime = strtotime('-1 month');
            $endtime = time();
        }

        if (!empty($_GPC['time']['start']) && !empty($_GPC['time']['end'])) {
            $starttime = strtotime($_GPC['time']['start']);
            $endtime = strtotime($_GPC['time']['end']);
            $condition .= " AND dm.createtime >= :starttime AND dm.createtime <= :endtime ";
            $params[':starttime'] = $starttime;
            $params[':endtime'] = $endtime;
        }
        if ($_GPC['level'] != '') {
            $condition.=' and level=' . intval($_GPC['level']);
        }
        $join = '';
        if ($_GPC['groupid'] != '') {
            $condition.=' and find_in_set('.intval($_GPC['groupid']).',groupid) ';
            $join .= " left join (select * from " . tablename('ewei_shop_member_group_log') . " order by log_id desc limit 1 ) glog on (glog.openid = dm.openid) and glog.group_id = ".(int)$_GPC['groupid'];
        }
        if ($_GPC['followed'] != '') {
            if ($_GPC['followed'] == 2) {
                $condition.=' and f.follow=0 and f.unfollowtime<>0';
            } else {
                $condition.=' and f.follow=' . intval($_GPC['followed']). ' and f.unfollowtime=0 ';
            }
            $join .= " join " . tablename('mc_mapping_fans') . " f on f.openid=dm.openid";
        }
        if ($_GPC['isblack'] != '') {
            $condition.=' and dm.isblack=' . intval($_GPC['isblack']);
        }
        $sql = "select * ,dm.openid from " . tablename('ewei_shop_member') . " dm {$join} where 1 {$condition}  ORDER BY dm.id DESC,dm.createtime DESC ";
        if (empty($_GPC['export'])) {
            $sql.=" limit " . ($pindex - 1) * $psize . ',' . $psize;
        }else{
            ini_set('memory_limit','-1');
        }

        $list = pdo_fetchall($sql, $params);
        $list_group = array();
        $list_level = array();
        $list_agent = array();
        $list_fans = array();
        foreach ($list as $val) {
            $list_group[] = trim($val['groupid'],',');
            $list_level[] = trim($val['level'],',');
            $list_agent[] = trim($val['agentid'],',');
            $list_fans[] = trim($val['openid'],',');
        }
        $memberids = array_keys($list);
        isset($list_group) && $list_group = array_values(array_filter($list_group));
        if (!empty($list_group)){
            $res_group = pdo_fetchall("select id,groupname from " . tablename('ewei_shop_member_group') . " where id in (".implode(',',$list_group).")",array(),'id');
        }
        isset($list_level) && $list_level = array_values(array_filter($list_level));
        if (!empty($list_level)){
            $res_level = pdo_fetchall("select id,levelname from " . tablename('ewei_shop_member_level') . " where id in (".implode(',',$list_level).")",array(),'id');
        }
        isset($list_agent) && $list_agent = array_values(array_filter($list_agent));
        if (!empty($list_agent)){
            $res_agent = pdo_fetchall("select id,nickname as agentnickname,avatar as agentavatar from " . tablename('ewei_shop_member') . " where id in (".implode(',',$list_agent).")",array(),'id');
        }
        isset($list_fans) && $list_fans = array_values(array_filter($list_fans));
        if (!empty($list_fans)){
            $res_fans = pdo_fetchall("select fanid,openid,follow as followed, unfollowtime from " . tablename('mc_mapping_fans') . " where openid in ('".implode('\',\'',$list_fans)."') and uniacid = :uniacid",array('uniacid'=>$_W['uniacid']),'openid');
        }
        $shop = m('common')->getSysset('shop');
        foreach ($list as &$row) {
            if(isset($row['groupid'])){
                $groupid= explode(',', $row['groupid']);
            }
            $row['groupname'] = '';
            if(is_array($groupid)){
               foreach($groupid as $key=>$value){
                  $row['groupname'] .= $res_group[$value]['groupname'].',';
               }
                $row['groupname'] = substr($row['groupname'],0,-1);
            }
            $row['levelname'] = isset($res_level[$row['level']]) ? $res_level[$row['level']]['levelname'] : '';
            $row['agentnickname'] = isset($res_agent[$row['agentid']]) ? $res_agent[$row['agentid']]['agentnickname'] : '';
            $row['agentnickname'] = str_replace('"', "",$row['agentnickname']);
            $row['agentavatar'] = isset($res_agent[$row['agentid']]) ? $res_agent[$row['agentid']]['agentavatar'] : '';
            $row['followed'] = isset($res_fans[$row['openid']]) ? $res_fans[$row['openid']]['followed'] : '';
            $row['unfollowtime'] = isset($res_fans[$row['openid']]) ? $res_fans[$row['openid']]['unfollowtime'] : '';
            $row['fanid'] = isset($res_fans[$row['openid']]) ? $res_fans[$row['openid']]['fanid'] : '';
            $row['levelname'] = empty($row['levelname']) ? (empty($shop['levelname']) ? '普通会员' : $shop['levelname']) : $row['levelname'];
            $row['ordercount'] = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $row['openid']));
            $row['ordermoney'] = pdo_fetchcolumn('select sum(price) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $row['openid']));
            $row['credit1'] = m('member')->getCredit($row['openid'], 'credit1');
            $row['credit2'] = m('member')->getCredit($row['openid'], 'credit2');
        }
        unset($row);

//导出Excel
        if ($_GPC['export'] == '1') {

            plog('member.list', '导出会员数据');
            foreach ($list as &$row) {
                $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
                $row['groupname'] = empty($row['groupname']) ? '无分组' : $row['groupname'];
                $row['levelname'] = empty($row['levelname']) ? '现金价会员' : $row['levelname'];
                $row['realname'] = str_replace('=', "", $row['realname']);
                $row['nickname'] = str_replace('=', "", $row['nickname']);
                $row['remark'] = trim($row['content']);
                $row['email'] = trim($row['email']);

                $row['company'] = trim($row['company']);
                $row['contact_name'] = trim($row['contact_name']);
                $row['contact_phone'] = trim($row['contact_phone']);
                $row['company_address'] = trim($row['company_address']).' '.trim($row['company_areas']);
                $row['yao_card'] = trim($row['yao_card']);
                $row['yao_card_time'] = date('Y-m-d', $row['yao_card_time']);
                $row['gsp_card'] = trim($row['gsp_card']);
                $row['gsp_card_time'] = date('Y-m-d', $row['gsp_card_time']);
                $row['business_card'] = trim($row['business_card']);
                $row['is_shen'] = $row['is_shen']==0?'待审核':'已审核';
                $row['isblack'] = $row['isblack']==0?'否':'是';
                $row['range'] = '销售部';
                $row['addr'] = mb_substr($row['company_address'],8,3,'utf-8');
                $row['business'] = '开户人:'.$row['opener'].'  业务员:'.$row['salesman'].'  跟单员:'.$row['gdman'].'  上级经理:'.$row['regional'];
            }
            unset($row);

            m('excel')->export($list, array(
                "title" => "会员数据-" . date('Y-m-d-H-i', time()),
                "columns" => array(
                    array('title' => '昵称', 'field' => 'nickname', 'width' => 12),
//                    array('title' => '姓名', 'field' => 'realname', 'width' => 12),
                    array('title' => '手机号(账号)', 'field' => 'mobile', 'width' => 12),
//                    array('title' => 'openid', 'field' => 'openid', 'width' => 24),
                    array('title' => '邮箱', 'field' => 'email', 'width' => 24),
                    array('title' => '区域', 'field' => 'range', 'width' => 12),
                    array('title' => '所在地区', 'field' => 'addr', 'width' => 12),
                    array('title' => '会员等级', 'field' => 'levelname', 'width' => 12),
                    array('title' => '会员分组', 'field' => 'groupname', 'width' => 12),
                    array('title' => '注册时间', 'field' => 'createtime', 'width' => 12),
                    array('title' => '企业名称', 'field' => 'company', 'width' => 24),
                    array('title' => '企业联系人', 'field' => 'contact_name', 'width' => 12),
                    array('title' => '联系人电话', 'field' => 'contact_phone', 'width' => 12),
                    array('title' => '企业地址', 'field' => 'company_address', 'width' => 24),
//                    array('title' => '积分', 'field' => 'credit1', 'width' => 12),
//                    array('title' => '余额', 'field' => 'credit2', 'width' => 12),
                    array('title' => '成交订单数', 'field' => 'ordercount', 'width' => 12),
                    array('title' => '成交总金额', 'field' => 'ordermoney', 'width' => 12),
                    array('title' => '备注', 'field' => 'remark', 'width' => 24),
                    array('title' => '药品许可证', 'field' => 'yao_card', 'width' => 12),
                    array('title' => '药品许可证有效期', 'field' => 'yao_card_time', 'width' => 12),
                    array('title' => 'GSP证书', 'field' => 'gsp_card', 'width' => 12),
                    array('title' => 'GSP证书有效期', 'field' => 'gsp_card_time', 'width' => 12),
                    array('title' => '营业执照号码', 'field' => 'business_card', 'width' => 12),
                    array('title' => '审核', 'field' => 'is_shen', 'width' => 12),
                    array('title' => '黑名单', 'field' => 'isblack', 'width' => 12),
                    array('title' => '业务', 'field' => 'business', 'width' => 24),

                )
            ));
        }

        $open_redis = function_exists('redis') && !is_error(redis());
        if($join == "" && $condition == " and dm.uniacid=:uniacid"){
            if($open_redis) {
                $redis_key = "ewei_{$_W['uniacid']}_member_list";
                $total = m('member')->memberRadisCount($redis_key);
                if(!$total){
                    $total = pdo_fetchcolumn("select count(*) from" . tablename('ewei_shop_member') . " dm {$join} where 1 {$condition} ", $params);
                    m('member') -> memberRadisCount($redis_key,$total);
                }
            }else{
                $total = pdo_fetchcolumn("select count(*) from" . tablename('ewei_shop_member') . " dm {$join} where 1 {$condition} ", $params);
            }
        }else{
            $total = pdo_fetchcolumn("select count(*) from" . tablename('ewei_shop_member') . " dm {$join} where 1 {$condition} ", $params);
        }
        $pager = pagination2($total, $pindex, $psize);

//是否开启分销
        $opencommission = false;
        $plug_commission = p('commission');
        if ($plug_commission) {
            $comset = $plug_commission->getSet();
            if (!empty($comset)) {
                $opencommission = true;
            }
        }

        $groups = m('member')->getGroups();
        $levels = m('member')->getLevels();

        $set = m('common')->getSysset();
        $default_levelname = empty($set['shop']['levelname']) ? '普通等级' : $set['shop']['levelname'];

        include $this->template('member/list');
    }

    function detail() {

        global $_W, $_GPC;

        $area_set = m('util')->get_area_config_set();

        $new_area = intval($area_set['new_area']);

        $shop = $_W['shopset']['shop'];
        $hascommission = false;
        $plugin_com = p('commission');

        if ($plugin_com) {
            $plugin_com_set = $plugin_com->getSet();
            $hascommission = !empty($plugin_com_set['level']);
        }
        $plugin_globonus = p('globonus');
        if ($plugin_globonus) {
            $plugin_globonus_set = $plugin_globonus->getSet();
            $hasglobonus = !empty($plugin_globonus_set['open']);
        }

        $plugin_author = p('author');
        if ($plugin_author) {
            $plugin_author_set = $plugin_author->getSet();
            $hasauthor = !empty($plugin_author_set['open']);
        }

        $plugin_abonus = p('abonus');
        if ($plugin_abonus) {
            $plugin_abonus_set = $plugin_abonus->getSet();
            $hasabonus = !empty($plugin_abonus_set['open']);
        }


        $id = intval($_GPC['id']);


        if ($hascommission) {
            $agentlevels = $plugin_com->getLevels();
        }

        if ($hasglobonus) {
            $partnerlevels = $plugin_globonus->getLevels();
        }

        if ($hasabonus) {
            $aagentlevels = $plugin_abonus->getLevels();
        }

        $member = m('member')->getMember($id);

        if (false == $member) {
            include $this->template('member/list/notFound');
            die;
        }

        if ($hascommission) {
            $member = $plugin_com->getInfo($id, array('total', 'pay'));
        }

        $member['self_ordercount'] = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
        $member['self_ordermoney'] = pdo_fetchcolumn('select sum(price) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));

        if (!empty($member['agentid'])) {
            $parentagent = m('member')->getMember($member['agentid']);
        }

        $order = pdo_fetch('select finishtime from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status>=1 Limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
        $member['last_ordertime'] = $order['finishtime'];

        if($hasglobonus){
            $bonus = $plugin_globonus->getBonus($member['openid'],array('ok'));
            $member['bonusmoney'] = $bonus['ok'];
        }

        if($hasabonus){
            $bonus = $plugin_abonus->getBonus($member['openid'],array('ok','ok1','ok2','ok3'));
            $member['abonus_ok'] = $bonus['ok'];
            $member['abonus_ok1'] = $bonus['ok1'];
            $member['abonus_ok2'] = $bonus['ok2'];
            $member['abonus_ok3'] = $bonus['ok3'];

            $member['aagentprovinces'] = iunserializer($member['aagentprovinces']);
            $member['aagentcitys'] = iunserializer($member['aagentcitys']);
            $member['aagentareas'] = iunserializer($member['aagentareas']);
        }
        $followed = m('user')->followed($member['openid']);

        $plugin_sns = p('sns');
        if ($plugin_sns) {
            $plugin_sns_set = $plugin_sns->getSet();
            $sns_member = pdo_fetch('select * from '.tablename('ewei_shop_sns_member')." where openid=:openid and uniacid=:uniacid limit 1",array(':openid'=>$member['openid'],':uniacid'=>$_W['uniacid']));

            $sns_member['postcount'] = pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_sns_post').' where uniacid=:uniacid and openid=:openid and pid=0 and deleted = 0 and checked=1',array(':uniacid'=>$_W['uniacid'],':openid'=>$member['openid']));
            $sns_member['replycount'] = pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_sns_post').' where uniacid=:uniacid and openid=:openid and pid>0 and deleted = 0 and checked=1',array(':uniacid'=>$_W['uniacid'],':openid'=>$member['openid']));

            $hassns = !empty($sns_member);
            if($hassns){
                $snslevels = $plugin_sns->getLevels();
            }
        }

        //自定义表单
        $diyform_flag = 0;
        $diyform_flag_commission = 0;
        $diyform_flag_globonus = 0;
        $diyform_flag_abonus = 0;
        $diyform_flag_dividend = 0;
        $diyform_plugin = p('diyform');
        if ($diyform_plugin) {

            if (!empty($member['diymemberdata'])) {
                $diyform_flag = 1;
                $fields = iunserializer($member['diymemberfields']);
            }

            if (!empty($member['diycommissiondata'])) {

                $diyform_flag_commission = 1;
                $cfields = iunserializer($member['diycommissionfields']);
            }
            if (!empty($member['diyheadsfields'])) {
                $diyform_flag_dividend = 1;
                $dfields = iunserializer($member['diyheadsfields']);
            }

            if (!empty($member['diyglobonusdata'])) {

                $diyform_flag_globonus = 1;
                $gfields = iunserializer($member['diyglobonusfields']);
            }

            if (!empty($member['diyaagentdata'])) {

                $diyform_flag_abonus = 1;
                $aafields = iunserializer($member['diyaagentfields']);
            }
        }
        $groups = m('member')->getGroups();
        $levels = m('member')->getLevels();

        $openbind = false;
        if((empty($_W['shopset']['app']['isclose']) && !empty($_W['shopset']['app']['openbind'])) || !empty($_W['shopset']['wap']['open'])){
            $openbind = true;
        }

        //2019-8-12  修改周期付款
        if(!empty($member['branch_day'])){
            $member['branch_day'] = unserialize($member['branch_day']);
        }


        if ($_W['ispost']) {

            $data = is_array($_GPC['data']) ? $_GPC['data'] : array();
            if($data['maxcredit']<0){
                $data['maxcredit'] = 0;
            }

            //周期付款处理
            if(!empty($data['branch_day'])){
                $data['branch_day'] = serialize($data['branch_day']);
            }

            
            if($openbind){

                if(!empty($data['mobileverify'])){
                    if(empty($data['mobile'])){
                        show_json(0, "绑定手机号请先填写用户手机号!");
                    }
                    $m = pdo_fetch('select id from ' . tablename('ewei_shop_member') . ' where mobile=:mobile and mobileverify=1 and uniacid=:uniaicd limit 1 ', array(':mobile'=>$data['mobile'], ':uniaicd'=>$_W['uniacid']));
                    if(!empty($m) && $m['id']!=$id){
                        show_json(0, "此手机号已绑定其他用户!(uid:".$m['id'].")");
                    }
                }

                $data['pwd'] = trim($data['pwd']);
                if(!empty($data['pwd'])){
                    $salt = $member['salt'];
                    if (empty($salt)) {
                        //生成识别码
                        $salt = m('account')->getSalt();
                    }
                    $data['pwd'] = md5($data['pwd'] . $salt);
                    $data['salt'] = $salt;
                }else{
                    unset($data['pwd']);
                    unset($data['salt']);
                }
            }

            if (is_array($_GPC['data']['groupid'])){
                $data['groupid'] = implode(',',$_GPC['data']['groupid']);
            }
            if(empty($data['groupid'])){
                $data['groupid']='';
            }

            pdo_update('ewei_shop_member', $data, array('id' => $id, 'uniacid' => $_W['uniacid']));

            $member =array_merge($member,$data);

            plog('member.list.edit', "修改会员资料  ID: {$member['id']} <br/> 会员信息:  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");

            //分销资料
            if ($hascommission) {
                if (cv('commission.agent.edit')) {
                    $adata = is_array($_GPC['adata']) ? $_GPC['adata'] : array();
                    if (!empty($adata)) {

                        //  判断修改上线权限 并写日志
                        if($adata['agentid']!=$member['agentid']){

                                //重新创建关系树，如果关系树有错误则提示
                                if(p('commission')){
                                    p('commission') -> delRelation($member['id']);
                                    $mem = p('commission') -> saveRelation($member['id'],$adata['agentid']);
                                    if(is_array($mem)){
                                        show_json(-1, "保存错误！". "<br/>请修改<a style='color: #259fdc;' target='_blank' href='".webUrl('member/list/detail', array('id' => $mem['id']))."'>会员(".$mem['nickname'].")</a>的上级分销商!");
                                    }
                                }

                            if(cv('commission.agent.changeagent')){

                                plog('commission.agent.changeagent', "修改上级分销商 <br/> 会员信息:  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']} <br/>上级ID: {$member['agentid']} -> 新上级ID: {$adata['agentid']}; <br/> 固定上级: ".($member['fixagentid']?'是':'否')." -> ".($adata['fixagentid']?'是':'否') );

                            }else{
                                $adata['agentid']=$member['agentid'];
                            }
                        }

                        $agent_flag = 0;
                        $cmember_plugin = p('cmember');
                        if ($cmember_plugin) {
                            $adata['cmemberagent'] = $adata['cmemberagent'];
                            $adata['agentnotupgrade'] = 1;
                            if($member['level'] == 0 && intval($data['level']) > 0) {
                                $agent_flag = 1;
                            }

                            if (intval($data['level']) > 0) {
                                $adata['isagent'] = 1;
                                $adata['status'] = 1;

                                if(!empty($adata['agentid'])) {
                                    $cmemberuid = $cmember_plugin->getCmemberuid($member['id']);
                                    if ($cmemberuid > 0) {
                                        $adata['cmemberuid'] = $cmemberuid;
                                    }
                                }
                            } else {
                                $adata['isagent'] = 0;
                                $adata['status'] = 0;
                            }
                        } else {
                            if (empty($_GPC['oldstatus']) && $adata['status'] == 1) {
                                $agent_flag = 1;
                            }
                        }

                        if (!empty($agent_flag)) {
                            $time = time();
                            $adata['agenttime'] = time();
                            //成为分销商消息通知
                            $plugin_com->sendMessage($member['openid'], array('nickname' => $member['nickname'], 'agenttime' => $time), TM_COMMISSION_BECOME);
                            plog('commission.agent.check', "审核分销商 <br/>分销商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        }
                        plog('commission.agent.edit', "修改分销商 <br/>分销商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        pdo_update('ewei_shop_member', $adata, array('id' => $id, 'uniacid' => $_W['uniacid']));
                        if($adata['agentid']!=$member['agentid']){
                            if(p('dividend')){
                                p('commission') -> delRelation($member['id']);
                                p('commission') -> saveRelation($member['id'],$adata['agentid'],1);

                                $dividend = pdo_fetch('select id,isheads,headsid,headsstatus from '.tablename('ewei_shop_member').' where id = :id',array(':id'=>$adata['agentid']));
                                $dividend_init = pdo_fetch('select * from '.tablename('ewei_shop_dividend_init').' where headsid = :headsid',array(':headsid'=>$adata['agentid']));
                                if(!empty($dividend['isheads']) && !empty($dividend['headsstatus']) && !empty($dividend_init['status'])){
                                    pdo_update('ewei_shop_member',array('headsid'=>$adata['agentid']),array('id'=>$member['id'],'uniacid'=>$_W['uniacid']));
                                    $data = pdo_fetchall('select id from '.tablename('ewei_shop_commission_relation').' where pid = :pid',array(':pid'=>$member['id']));
                                    if(!empty($data)){
                                        $ids = array();
                                        foreach($data as $k => $v){
                                            $ids[] = $v['id'];
                                        }
                                        pdo_update('ewei_shop_member', array("headsid"=>$adata['agentid']), array('id' =>$ids));
                                    }
                                }else if(empty($dividend['isheads']) && !empty($dividend['headsid'])){
                                    pdo_update('ewei_shop_member',array('headsid'=>$dividend['headsid']),array('id'=>$member['id'],'uniacid'=>$_W['uniacid']));
                                    $data = pdo_fetchall('select id from '.tablename('ewei_shop_commission_relation').' where pid = :pid',array(':pid'=>$member['id']));
                                    if(!empty($data)){
                                        $ids = array();
                                        foreach($data as $k => $v){
                                            $ids[] = $v['id'];
                                        }
                                        pdo_update('ewei_shop_member', array("headsid"=>$dividend['headsid']), array('id' =>$ids));
                                    }
                                }else{
                                    pdo_update('ewei_shop_member',array('headsid'=>0),array('id'=>$member['id'],'uniacid'=>$_W['uniacid']));
                                    $data = pdo_fetchall('select id from '.tablename('ewei_shop_commission_relation').' where pid = :pid',array(':pid'=>$member['id']));
                                    if(!empty($data)){
                                        $ids = array();
                                        foreach($data as $k => $v){
                                            $ids[] = $v['id'];
                                        }
                                        pdo_update('ewei_shop_member', array("headsid"=>0), array('id' =>$ids));
                                    }
                                }
                            }
                        }
                        if (!empty($agent_flag)) {
                            //检测升级
                            if (!empty($member['agentid'])) {
                                $plugin_com->upgradeLevelByAgent($member['agentid']);

                                if(p('globonus')){
                                    p('globonus')->upgradeLevelByAgent($member['agentid']);
                                }
                                //创始人升级
                                if(p('author')){
                                    p('author')->upgradeLevelByAgent($member['agentid']);
                                }
                            }
                        }
                    }
                }
            }

            //股东资料
            if($hasglobonus){
                if (cv('globonus.partner.check')) {
                    $gdata = is_array($_GPC['gdata']) ? $_GPC['gdata'] : array();
                    if (!empty($gdata)) {

                        if (empty($_GPC['oldpartnerstatus']) && $gdata['partnerstatus'] == 1) {

                            $time = time();
                            $gdata['partnertime'] = time();
                            //成为股东消息通知
                            $plugin_globonus->sendMessage($member['openid'], array('nickname' => $member['nickname'], 'partnertime' => $time), TM_GLOBONUS_BECOME);
                            plog('globonus.partner.check', "审核股东 <br/>股东信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        }

                        plog('globonus.partner.edit', "修改股东 <br/>股东信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        pdo_update('ewei_shop_member', $gdata, array('id' => $id, 'uniacid' => $_W['uniacid']));
                    }
                }
            }

            //联合创始人资料
            if($hasauthor){
                if (cv('author.partner.check')) {
                    $author_data = is_array($_GPC['authordata']) ? $_GPC['authordata'] : array();
                    if (!empty($author_data)) {

                        if (empty($_GPC['oldauthorstatus']) && $author_data['authorstatus'] == 1) {

                            $author_data['authortime'] = time();
                            if (method_exists($plugin_author,'changeAuthorId')){
                                $plugin_author->changeAuthorId($member['id']);
                            }
                            //成为创始人消息通知
                            $plugin_author->sendMessage($member['openid'], array('nickname' => $member['nickname'], 'authortime' => time()), TM_AUTHOR_BECOME);
                            plog('author.partner.check', "审核创始人 <br/>创始人信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        }

                        if ($_GPC['oldauthorstatus'] == 1 && $author_data['authorstatus'] == 0) {
                            if (method_exists($plugin_author,'changeAuthorId')){
                                $plugin_author->changeAuthorId($member['id'],intval($member['authorid']));
                            }
                        }

                        plog('author.partner.edit', "修改创始人 <br/>创始人信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        pdo_update('ewei_shop_member', $author_data, array('id' => $id, 'uniacid' => $_W['uniacid']));
                    }
                }
            }


            //代理商资料
            if($hasabonus){
                if (cv('abonus.agent.check')) {
                    $aadata = is_array($_GPC['aadata']) ? $_GPC['aadata'] : array();
                    if (!empty($aadata)) {

                        $aagentprovinces =  is_array($_GPC['aagentprovinces'])?$_GPC['aagentprovinces']:array();
                        $aagentcitys =  is_array($_GPC['aagentcitys'])?$_GPC['aagentcitys']:array();
                        $aagentareas =  is_array($_GPC['aagentareas'])?$_GPC['aagentareas']:array();

                        $aadata['aagentprovinces'] =iserializer($aagentprovinces);
                        $aadata['aagentcitys'] = iserializer($aagentcitys);
                        $aadata['aagentareas'] =iserializer($aagentareas);
                        if($aadata['aagenttype']==2){

                            //市级删除省级代理地区
                            $aadata['aagentprovinces'] = iserializer(array());

                        } else if($aadata['aagenttype']==3){
                            //区级代理删除省级及市级代理地区
                            $aadata['aagentprovinces'] = iserializer(array());
                            $aadata['aagentcitys'] = iserializer(array());
                        }
                        $areas = array_merge($aagentprovinces, $aagentcitys,$aagentareas );

                        if (empty($_GPC['oldaagentstatus']) && $aadata['aagentstatus'] == 1) {

                            $time = time();
                            $aadata['aagenttime'] = time();
                            //成为代理商消息通知
                            $plugin_abonus->sendMessage($member['openid'],
                                array('nickname' => $member['nickname'],
                                    'aagenttype' => $aadata['aagenttype'],
                                    'aagenttime' => $time,
                                    'aagentareas'=>implode( "; ", $areas)
                                ), TM_ABONUS_BECOME);
                            plog('abounus.agent.check', "审核代理商 <br/>代理商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                        }


                        $log = "修改代理商 <br/>代理商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}";
                        if(is_array($_GPC['aagentprovinces'])) {
                            $log .= "<br/>代理省份:" . implode(',', $_GPC['aagentprovinces']);
                        }
                        if(is_array($_GPC['aagentcitys'])) {
                            $log .= "<br/>代理城市:" . implode(',', $_GPC['aagentcitys']);
                        }
                        if(is_array($_GPC['aagentareas'])) {
                            $log .= "<br/>代理地区:" . implode(',', $_GPC['aagentareas']);
                        }

                        plog('abounus.agent.edit', $log);
                        pdo_update('ewei_shop_member', $aadata, array('id' => $id, 'uniacid' => $_W['uniacid']));
                    }
                }
            }

            //更新用户会员卡信息
            //com('wxcard')->updateMemberCardByOpenid($member['openid']);
            com_run('wxcard::updateMemberCardByOpenid',$member['openid']);

            //社区资料
            if($hassns){
                if (cv('sns.member.edit')) {
                    $snsdata = is_array($_GPC['snsdata']) ? $_GPC['snsdata'] : array();
                    if (!empty($snsdata)) {
                        plog('sns.member.edit', "修改会员资料 ID: {$sns_member['id']}");
                        pdo_update('ewei_shop_sns_member', $snsdata, array('id' => $sns_member['id'], 'uniacid' => $_W['uniacid']));
                    }
                }
            }

            show_json(1);
        }


        if ($hascommission) {
            $agentlevels = $plugin_com->getLevels();
        }

        if ($hasglobonus) {
            $partnerlevels = $plugin_globonus->getLevels();
        }

        if ($hasauthor) {
            $authorlevels = $plugin_author->getLevels();
        }

        if ($hasabonus) {
            $aagentlevels = $plugin_abonus->getLevels();
        }

        if (!empty($member['agentid'])) {
            $parentagent = m('member')->getMember($member['agentid']);
        }

        $order = pdo_fetch('select finishtime from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3 order by id desc limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
        $member['last_ordertime'] = $order['finishtime'];

        if($hasglobonus){
            $bonus = $plugin_globonus->getBonus($member['openid'],array('ok'));
            $member['bonusmoney'] = $bonus['ok'];
        }

        if($hasauthor){
            $bonus = $plugin_author->getBonus($member['openid'],array('ok'));
            $member['authormoney'] = $bonus['ok'];
        }

        if($hasabonus){
            $bonus = $plugin_abonus->getBonus($member['openid'],array('ok','ok1','ok2','ok3'));
            $member['abonus_ok'] = $bonus['ok'];
            $member['abonus_ok1'] = $bonus['ok1'];
            $member['abonus_ok2'] = $bonus['ok2'];
            $member['abonus_ok3'] = $bonus['ok3'];

            $member['aagentprovinces'] = iunserializer($member['aagentprovinces']);
            $member['aagentcitys'] = iunserializer($member['aagentcitys']);
            $member['aagentareas'] = iunserializer($member['aagentareas']);
        }

        //自定义表单
        $diyform_flag = 0;
        $diyform_flag_commission = 0;
        $diyform_flag_globonus = 0;
        $diyform_flag_abonus = 0;
        $diyform_plugin = p('diyform');
        if ($diyform_plugin) {

            if (!empty($member['diymemberdata'])) {
                $diyform_flag = 1;
                $fields = iunserializer($member['diymemberfields']);
            }

            if (!empty($member['diycommissiondata'])) {

                $diyform_flag_commission = 1;
                $cfields = iunserializer($member['diycommissionfields']);
            }

            if (!empty($member['diyglobonusdata'])) {

                $diyform_flag_globonus = 1;
                $gfields = iunserializer($member['diyglobonusfields']);
            }

            if (!empty($member['diyauthordata'])) {
                $diyform_flag_author = 1;
                $authorfields = iunserializer($member['diyauthordata']);
            }

            if (!empty($member['diyaagentdata'])) {

                $diyform_flag_abonus = 1;
                $aafields = iunserializer($member['diyaagentfields']);
            }
        }

        include $this->template();
    }

    //企业信息
    public function company()
    {
        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        $member = m('member')->getMember($id);
        
        $imgs_new = unserialize($member['orther_img']);
        $member['food_img'] = $imgs_new['food_img'];
        $member['arrive_img'] = $imgs_new['arrive_img'];
        $member['appliance_img'] = $imgs_new['appliance_img'];

        $area_set = m("util")->get_area_config_set();
        $new_area = intval($area_set["new_area"]);
        $address_street = intval($area_set["address_street"]);
        $member['bus_cate'] = explode(',',$member['bus_cate']);
        if($member['company_address']){
            $x = explode(' ',$member['company_address']);
            $member['p'] = $x[0];
            $member['c'] = $x[1];
            $member['a'] = $x[2];
        }else{
            $member['p'] = '';
            $member['c'] = '';
            $member['a'] = '';
        }
        //经营类别
        $sell_cates = m('goods')->sellCate();

        if( $_W["ispost"] )
        {
            $data = is_array($_GPC['data']) ? $_GPC['data'] : array();

            //判断
            if($data['company'] == '')show_json(0, "企业名称不能为空!");
            if($data['contact_name'] == '')show_json(0, "姓名不能为空!");
            if($data['contact_phone'] == '')show_json(0, "手机号码不能为空!");
            if(!preg_match("/^1[345678]{1}\d{9}$/",$data['contact_phone']))show_json(0, "请输入有效的手机号码!");


            if($data['p'] == '')show_json(0, "请选择省!");
            if($data['c'] == '')show_json(0, "请选择市!");
            if($data['a'] == '')show_json(0, "请选择区!");

            if($data['company_areas'] == '')show_json(0, "详细地址不能为空!");

            if($data['invoice_type'] == '')show_json(0, "发票类型不能为空!");
            if($data['yao_card'] == '')show_json(0, "药品许可证不能为空!");
            if($data['yao_card_time'] == '')show_json(0, "药品许可证有效期不能为空!");
            if($data['gsp_card'] == '')show_json(0, "GSP证书不能为空!");
            if($data['gsp_card_time'] == '')show_json(0, "GSP证书有效期不能为空!");
            if($data['business_card'] == '')show_json(0, "营业执照号码不能为空!");
            if($data['business_img'] == '')show_json(0, "请上传营业执照!");
            if($data['yao_card_img'] == '')show_json(0, "请上传药品许可证图片!");

            if($data['quality_img'] == '')show_json(0, "请上传质量保证协议图片!");
            if($data['buyer_img'] == '')show_json(0, "请上传采购委托书图片!");
            if($data['buyerid_img'] == '')show_json(0, "请上传采购委托人身份证图片!");
            if($data['report_img'] == '')show_json(0, "请上传年度报告图片!");
            if($data['arriveid_img'] == '')show_json(0, "请上传收货委托人身份证图片!");
            if($data['bill_img'] == '')show_json(0, "请上传发票资料图片!");
            if($data['gsp_img'] == '')show_json(0, "请上传GSP证书图片!");
            $food_img = empty($data['food_img']) ? ' ' : $data['food_img'];
            $appliance_img = empty($data['appliance_img']) ? ' ' : $data['appliance_img']; 
            $food_img = empty($data['food_img']) ? ' ' : $data['food_img'];
            $arrive_img = empty($data['arrive_img']) ? ' ' : $data['arrive_img'];
            $appliance_img = empty($data['appliance_img']) ? ' ' : $data['appliance_img']; 
            $orther_img = array('food_img'=>$food_img,'arrive_img'=>$arrive_img,'appliance_img'=>$appliance_img);
            $orther_img = serialize($orther_img);
            //是否勾选经营类别
            $bus_cate = '';
            if(isset($data['bus_cate']) && !empty($data['bus_cate'])){
                $bus_cate = implode(',',$data['bus_cate']);
            }

            $data1 = [
                'erp_user_id'=>$data['erp_user_id'],
                'company'=>$data['company'],
                'contact_name'=>$data['contact_name'],
                'contact_phone'=>$data['contact_phone'],
                'company_address'=>$data['p'].' '.$data['c'].' '.$data['a'],
                'company_areas'=>$data['company_areas'],
                'invoice_type'=>$data['invoice_type'],
                'yao_card'=>$data['yao_card'],
                'yao_card_time'=>strtotime($data['yao_card_time']),
                'gsp_card'=>$data['gsp_card'],
                'gsp_card_time'=>strtotime($data['gsp_card_time']),
                'business_card'=>$data['business_card'],
                'business_img'=>$data['business_img'],
                'yao_card_img'=>$data['yao_card_img'],
                'is_shen'=>$data['is_shen'],
                'delivery'=>$data['delivery'],
                'gsp_img' => $data['gsp_img'],
                'bill_img' => $data['bill_img'],
                'report_img' => $data['report_img'],
                'arriveid_img' => $data['arriveid_img'],
                'buyerid_img' => $data['buyerid_img'], 
                'buyer_img' => $data['buyer_img'],
                'quality_img' => $data['quality_img'],
                'orther_img' => $orther_img,
                'content'=>$data['content'],
                'bus_cate'=>$bus_cate,
                'opener'=>$data['opener'],
                'salesman'=>$data['salesman'],
                'gdman'=>$data['gdman'],
                'regional'=>$data['regional'],
            ];

            $re = pdo_update('ewei_shop_member', $data1, array('id' => $data['id']));
            if(!empty($re)){
                //添加企业地址为收货地址
                $uAddress = pdo_get('ewei_shop_member_address', array('uniacid' => $_W['uniacid'],'openid'=>$data['openid'],'isdefault'=>1), array('openid','id'));
                if($uAddress){
                    $us = [
                        'realname'=>$data['contact_name'],
                        'mobile'=>$data['contact_phone'],
                        'province'=>$data['p'],
                        'city'=>$data['c'],
                        'area'=>$data['a'],
                        'address'=>$data['company_areas'],
                    ];
                    pdo_update('ewei_shop_member_address', $us, array('id' => $uAddress['id']));
                }else{
                    $us = [
                        'uniacid'=>$_W['uniacid'],
                        'openid'=>$data['openid'],
                        'realname'=>$data['contact_name'],
                        'mobile'=>$data['contact_phone'],
                        'province'=>$data['p'],
                        'city'=>$data['c'],
                        'area'=>$data['a'],
                        'address'=>$data['company_areas'],
                        'isdefault'=>1,
                    ];
                    pdo_insert('ewei_shop_member_address', $us);
                }
            }


            show_json(1, array( "url" => webUrl("member.list.status0") ));

        }

        include $this->template();
    }
    //修改企业信息
    public function edit_company()
    {
        try{
            global $_W, $_GPC;
            exit( json_encode(['code'=>0,'message'=>'该方法已弃用']) );
            //判断
            if($_GPC['company'] == '')exit( json_encode(['code'=>0,'message'=>'企业名称不能为空']) );
            if($_GPC['contact_name'] == '')exit( json_encode(['code'=>0,'message'=>'姓名不能为空']) );
            if($_GPC['contact_phone'] == '')exit( json_encode(['code'=>0,'message'=>'手机号码不能为空']) );
            if(!preg_match("/^1[345678]{1}\d{9}$/",$_GPC['contact_phone']))exit( json_encode(['code'=>0,'message'=>'请输入有效的手机号码']) );
            if($_GPC['company_address'] == '')exit( json_encode(['code'=>0,'message'=>'企业地址不能为空']) );
            if($_GPC['invoice_type'] == '')exit( json_encode(['code'=>0,'message'=>'发票类型不能为空']) );
            if($_GPC['yao_card'] == '')exit( json_encode(['code'=>0,'message'=>'药品许可证不能为空']) );
            if($_GPC['yao_card_time'] == '')exit( json_encode(['code'=>0,'message'=>'药品许可证有效期不能为空']) );
            if($_GPC['gsp_card'] == '')exit( json_encode(['code'=>0,'message'=>'GSP证书不能为空']) );
            if($_GPC['gsp_card_time'] == '')exit( json_encode(['code'=>0,'message'=>'GSP证书有效期不能为空']) );
            if($_GPC['business_card'] == '')exit( json_encode(['code'=>0,'message'=>'营业执照号码不能为空']) );
            if($_GPC['business_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传营业执照']) );
            if($_GPC['yao_card_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传药品许可证图片']) );

            if($_GPC['quality_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传质量保证协议图片']) );
            if($_GPC['buyer_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传采购委托书图片']) );
            if($_GPC['buyerid_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传采购委托人身份证图片']) );
            if($_GPC['arrive_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传收货委托书图片']) );
            if($_GPC['arriveid_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传收货委托人身份证图片']) );
            if($_GPC['bill_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传发票资料图片']) );
            if($_GPC['gsp_img'] == '')exit( json_encode(['code'=>0,'message'=>'请上传GSP证书图片']) );
            $data = [
                'erp_user_id'=>$_GPC['erp_user_id'],
                'company'=>$_GPC['company'],
                'contact_name'=>$_GPC['contact_name'],
                'contact_phone'=>$_GPC['contact_phone'],
                'company_address'=>$_GPC['company_address'],
                'invoice_type'=>$_GPC['invoice_type'],
                'yao_card'=>$_GPC['yao_card'],
                'yao_card_time'=>strtotime($_GPC['yao_card_time']),
                'gsp_card'=>$_GPC['gsp_card'],
                'gsp_card_time'=>strtotime($_GPC['gsp_card_time']),
                'business_card'=>$_GPC['business_card'],
                'business_img'=>$_GPC['business_img'],
                'yao_card_img'=>$_GPC['yao_card_img'],
                'gsp_img' => $data['gsp_img'],
                'bill_img' => $data['bill_img'],
                'arrive_img' => $data['arrive_img'],
                'arriveid_img' => $data['arriveid_img'],
                'buyerid_img' => $data['buyerid_img'], 
                'buyer_img' => $data['buyer_img'],
                'quality_img' => $data['quality_img'],
                'is_shen'=>$_GPC['is_shen'],
                'content'=>$_GPC['content']
            ];

            pdo_update('ewei_shop_member', $data, array('id' => $_GPC['uid']));
            exit(json_encode(['code'=>1,'message'=>'修改成功']));
        }catch (Exception $e){
            exit(json_encode(['code'=>0,'message'=>'修改失败']));
        }
    }


    function view() {

        global $_W, $_GPC;

        $area_set = m('util')->get_area_config_set();

        $new_area = intval($area_set['new_area']);

        $shop = $_W['shopset']['shop'];
        $hascommission = false;
        $plugin_com = p('commission');

        if ($plugin_com) {
            $plugin_com_set = $plugin_com->getSet();
            $hascommission = !empty($plugin_com_set['level']);
        }
        $plugin_globonus = p('globonus');
        if ($plugin_globonus) {
            $plugin_globonus_set = $plugin_globonus->getSet();
            $hasglobonus = !empty($plugin_globonus_set['open']);
        }

        $plugin_author = p('author');
        if ($plugin_author) {
            $plugin_author_set = $plugin_author->getSet();
            $hasauthor = !empty($plugin_author_set['open']);
        }

        $plugin_abonus = p('abonus');
        if ($plugin_abonus) {
            $plugin_abonus_set = $plugin_abonus->getSet();
            $hasabonus = !empty($plugin_abonus_set['open']);
        }


        $id = intval($_GPC['id']);


        if ($hascommission) {
            $agentlevels = $plugin_com->getLevels();
        }

        if ($hasglobonus) {
            $partnerlevels = $plugin_globonus->getLevels();
        }

        if ($hasabonus) {
            $aagentlevels = $plugin_abonus->getLevels();
        }

        $member = m('member')->getMember($id);

        if ($hascommission) {
            $member = $plugin_com->getInfo($id, array('total', 'pay'));
        }

        $member['self_ordercount'] = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
        $member['self_ordermoney'] = pdo_fetchcolumn('select sum(price) from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));

        if (!empty($member['agentid'])) {
            $parentagent = m('member')->getMember($member['agentid']);
        }

        $order = pdo_fetch('select finishtime from ' . tablename('ewei_shop_order') . ' where uniacid=:uniacid and openid=:openid and status>=1 Limit 1', array(':uniacid' => $_W['uniacid'], ':openid' => $member['openid']));
        $member['last_ordertime'] = $order['finishtime'];

        if($hasglobonus){
            $bonus = $plugin_globonus->getBonus($member['openid'],array('ok'));
            $member['bonusmoney'] = $bonus['ok'];
        }

        if($hasabonus){
            $bonus = $plugin_abonus->getBonus($member['openid'],array('ok','ok1','ok2','ok3'));
            $member['abonus_ok'] = $bonus['ok'];
            $member['abonus_ok1'] = $bonus['ok1'];
            $member['abonus_ok2'] = $bonus['ok2'];
            $member['abonus_ok3'] = $bonus['ok3'];

            $member['aagentprovinces'] = iunserializer($member['aagentprovinces']);
            $member['aagentcitys'] = iunserializer($member['aagentcitys']);
            $member['aagentareas'] = iunserializer($member['aagentareas']);
        }

        $plugin_sns = p('sns');
        if ($plugin_sns) {
            $plugin_sns_set = $plugin_sns->getSet();
            $sns_member = pdo_fetch('select * from '.tablename('ewei_shop_sns_member')." where openid=:openid and uniacid=:uniacid limit 1",array(':openid'=>$member['openid'],':uniacid'=>$_W['uniacid']));

            $sns_member['postcount'] = pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_sns_post').' where uniacid=:uniacid and openid=:openid and pid=0 and deleted = 0 and checked=1',array(':uniacid'=>$_W['uniacid'],':openid'=>$member['openid']));
            $sns_member['replycount'] = pdo_fetchcolumn('select count(*) from '.tablename('ewei_shop_sns_post').' where uniacid=:uniacid and openid=:openid and pid>0 and deleted = 0 and checked=1',array(':uniacid'=>$_W['uniacid'],':openid'=>$member['openid']));

            $hassns = !empty($sns_member);
            if($hassns){
                $snslevels = $plugin_sns->getLevels();
            }
        }

        //自定义表单
        $diyform_flag = 0;
        $diyform_flag_commission = 0;
        $diyform_flag_globonus = 0;
        $diyform_flag_abonus = 0;
        $diyform_plugin = p('diyform');
        if ($diyform_plugin) {

            if (!empty($member['diymemberdata'])) {
                $diyform_flag = 1;
                $fields = iunserializer($member['diymemberfields']);
            }

            if (!empty($member['diycommissiondata'])) {

                $diyform_flag_commission = 1;
                $cfields = iunserializer($member['diycommissionfields']);
            }

            if (!empty($member['diyglobonusdata'])) {

                $diyform_flag_globonus = 1;
                $gfields = iunserializer($member['diyglobonusfields']);
            }

            if (!empty($member['diyaagentdata'])) {

                $diyform_flag_abonus = 1;
                $aafields = iunserializer($member['diyaagentfields']);
            }
        }
        $groups = m('member')->getGroups();
        $levels = m('member')->getLevels();

        $openbind = false;
        if((empty($_W['shopset']['app']['isclose']) && !empty($_W['shopset']['app']['openbind'])) || !empty($_W['shopset']['wap']['open'])){
            $openbind = true;
        }

        include $this->template();
    }

    function delete() {

        global $_W, $_GPC;
        $id = intval($_GPC['id']);
        if (empty($id)) {
            $id = is_array($_GPC['ids']) ? implode(',', $_GPC['ids']) : 0;
        }

        $members = pdo_fetchall("SELECT * FROM " . tablename('ewei_shop_member') . " WHERE id in( $id ) AND uniacid=" . $_W['uniacid']);
        foreach ($members as $member) {
            //pdo_update('ewei_shop_member',array('agentid'=>0),array('agentid'=>$member['id']));
            pdo_delete('ewei_shop_member', array('id' => $member['id']));
            plog('member.list.delete', "删除会员  ID: {$member['id']} <br/>会员信息: {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
            if(method_exists(m('member'),'memberRadisCountDelete')) {
                m('member')->memberRadisCountDelete(); //清除会员统计radis缓存
            }
        }
        show_json(1, array('url' => referer()));
    }

    function setblack() {

        global $_W, $_GPC;

        $id = intval($_GPC['id']);
        if (empty($id)) {
            $id = is_array($_GPC['ids']) ? implode(',', $_GPC['ids']) : 0;
        }
        $members = pdo_fetchall("select id,openid,nickname,realname,mobile from " . tablename('ewei_shop_member')  . " WHERE id in( $id ) AND uniacid=" . $_W['uniacid']);

        $black = intval($_GPC['isblack']);
        foreach($members as $member) {
            if (!empty($black)) {
                pdo_update('ewei_shop_member', array('isblack' => 1), array('id' => $member['id']));
                plog('member.list.edit', "设置黑名单 <br/>用户信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
            } else {
                pdo_update('ewei_shop_member', array('isblack' => 0), array('id' => $member['id']));
                plog('member.list.edit', "取消黑名单 <br/>用户信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
            }
        }
        show_json(1);
    }

    function changelevel() {
        global $_W, $_GPC;

        if($_W['ispost']){
            $toggle = trim($_GPC['toggle']);
            $ids = $_GPC['ids'];
            $levelid = $_GPC['level'];
            !strpos($levelid,',') && $levelid = intval($_GPC['level']);
            if(empty($ids) || !is_array($ids)){
                show_json(0, "请选择要操作的会员");
            }
            if(empty($toggle)){
                show_json(0, "请选择要操作的类型");
            }
            $ids = array_filter($ids);
            $idsstr = implode(',', $ids);
            $loginfo = "批量修改";
            if($toggle=='group'){
                if(!empty($levelid)){
                    $levelid_arr = explode(',',$levelid);
                    if(!empty($levelid_arr)){
                        foreach ($levelid_arr as $id){
                            $group = pdo_fetch('select * from ' . tablename('ewei_shop_member_group') . ' where id = :id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
                            if(empty($group)){
                                show_json(0, "此分组不存在");
                            }
                        }
                    }else{
                        show_json(0, "此分组不存在");
                    }
                }else{
                    $group = array('groupname'=>'无分组');
                }

                $loginfo .= "用户分组 分组名称：".$group['groupname'];
            }else{
                if(!empty($levelid)) {
                    $level = pdo_fetch('select * from ' . tablename('ewei_shop_member_level') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $levelid, ':uniacid' => $_W['uniacid']));
                    if (empty($level)) {
                        show_json(0, "此等级不存在");
                    }
                }else{
                    $set = m('common')->getSysset();
                    $level = array('levelname'=>empty($set['shop']['levelname']) ? '普通等级' : $set['shop']['levelname']);
                }
                $arr = array('level'=>$levelid);
                $loginfo .= "用户等级 等级名称：".$level['levelname'];
            }

            $changeids = array();

            $members = pdo_fetchall("select id,openid,nickname,realname,mobile from " . tablename('ewei_shop_member')  . " WHERE id in( $idsstr ) AND uniacid=" . $_W['uniacid']);
            if(!empty($members)){
                foreach ($members as $member) {
                    if ($toggle=='group'){
                        m('member')->setGroups($member['id'],$levelid,'管理员设置批量分组');
                    }else{
                        pdo_update('ewei_shop_member', $arr, array('id' => $member['id']));
                        $changeids[] = $member['id'];
                    }
                }
            }

            if(!empty($changeids)){
                $loginfo .= " 用户id：". implode(",", $changeids);
                plog('member.list.edit', $loginfo);
            }

            show_json(1);
        }


        include $this->template();
    }

    function query() {

        global $_W, $_GPC;
        $kwd = trim($_GPC['keyword']);
        $wechatid = intval($_GPC['wechatid']);
        if (empty($wechatid)) {
            $wechatid = $_W['uniacid'];
        }
        $params = array();
        $params[':uniacid'] = $wechatid;
        $condition = " and uniacid=:uniacid";
        if (!empty($kwd)) {
            $condition.=" AND ( `nickname` LIKE :keyword or `realname` LIKE :keyword or `mobile` LIKE :keyword )";
            $params[':keyword'] = "%{$kwd}%";
        }
        $ds = pdo_fetchall('SELECT id,avatar,nickname,openid,realname,mobile FROM ' . tablename('ewei_shop_member') . " WHERE 1 {$condition} order by createtime desc", $params);
        if ($_GPC['suggest']) {
            die(json_encode(array('value' => $ds)));
        }
        include $this->template();
    }
    

}
