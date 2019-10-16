<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}

class Kucun_EweiShopV2Page
{

    public $uniacid;
    public $merchid;
    public function __construct()
    {
        $this->uniacid = 8;
        $this->merchid = 0;
    }

    /*获取erp商品库存
     * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun
     * goodsStock 参数
     */
    public function main()
    {
        try {
            global $_W;
            global $_GPC;
            $input = file_get_contents("php://input");
            $data = json_decode($input,true);

            $totals = [];
            if(isset($data['goodsStock']) && !empty($data['goodsStock'])){
                foreach($data['goodsStock'] as $k=>$v){
                    if($v['stock'] <= 0){
                        $stock = 0;
                    }else{
                        $stock = $v['stock'];
                    }
                    array_push($totals,$v["inCode"]);
                    pdo_update("ewei_shop_goods", array( "total" =>$stock), array( "goodsisn" => $v["inCode"], "uniacid" => $this->uniacid, 'merchid'=>$this->merchid, 'deleted'=>0));
                }
                //没传的自动改为0
                m('goods')->changeZero($totals);
                //商品自动上下架
                m('goods')->changeStatus();
                $result = ['status'=>'success','msg'=>'库存更新成功'];
            }else{
                $result = ['status'=>'error','msg'=>'接收不到参数'];
            }
            echo json_encode($result);
            die;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }

    /*获取erp商品价格
     * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.price
     * goodsPrice 参数
     */
    public function price()
    {
        try {
            global $_W;
            global $_GPC;

            $input = file_get_contents("php://input");
            $data = json_decode($input,true);

            if(!empty($data['goodsPrice'])){

                //等级对应ID
                $le1 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>1), 'id');
                $le2 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>2), 'id');
                $le3 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>3), 'id');
                foreach($data['goodsPrice'] as $k=>$v){

                    $dis = [
                        'type'=>0,
                        'default'=>'',
                        'default_pay'=>sprintf("%.2f",$v['price']),
                        'level'.$le1=>'',
                        'level'.$le1.'_pay'=>($v['price2']!='' || $v['price2']>0)?sprintf("%.2f",$v['price2']):sprintf("%.2f",$v['price']),
                        'level'.$le2=>'',
                        'level'.$le2.'_pay'=>($v['price2']!='' || $v['price2']>0)?sprintf("%.2f",$v['price2']):sprintf("%.2f",$v['price']),//$v['price3']
                        'level'.$le3=>'',
                        'level'.$le3.'_pay'=>($v['price2']!='' || $v['price2']>0)?sprintf("%.2f",$v['price2']):sprintf("%.2f",$v['price']),//$v['price4']
                    ];
                    $pro = [
                        'marketprice'=>sprintf("%.2f",$v['price']),
                        'discounts'=>json_encode($dis)
                    ];
                    pdo_update("ewei_shop_goods", $pro, array( "goodsisn" => $v["inCode"], "uniacid" => $this->uniacid, 'merchid'=>$this->merchid));

                    //如果价格为空或0 则商品下架
                    if(empty($v['price']) || $v['price'] <= 0){
                        pdo_update("ewei_shop_goods", ['status'=>0], array( "goodsisn" => $v["inCode"], "uniacid" => $this->uniacid, 'merchid'=>$this->merchid));
                    }
                }

                $result = ['status'=>'success','msg'=>'价格更新成功'];

            }else{

                $result = ['status'=>'error','msg'=>'接收不到参数'];
            }

            echo json_encode($result);
            die;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }


    /*获取erp商品浏览等级
     * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.show
     * goodsProGrade 参数
     */
    public function show()
    {
        try {
            global $_W;
            global $_GPC;
            $result = ['status'=>'success','msg'=>'商品浏览等级'];
            echo json_encode($result);
            die;
            $input = file_get_contents("php://input");
            $data = json_decode($input,true);
            if(isset($data['goodsProGrade']) && !empty($data['goodsProGrade'])){

                //等级对应ID
                $le1 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>1), 'id');
                $le2 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>2), 'id');
                $le3 = pdo_getcolumn('ewei_shop_member_level', array("uniacid" => $this->uniacid,'level'=>3), 'id');
                foreach($data['goodsProGrade'] as $k=>$v){

                    //如果全部存在，则可以不用设置，默认显示全部
                    if(!strstr($v['proGrade'],'0,1,2,3')){
                        $showlevels = [];
                        if(strstr($v['proGrade'],'0')){
                            array_push($showlevels,0);
                        }
                        if(strstr($v['proGrade'],'1')){
                            array_push($showlevels,$le1);
                        }
                        if(strstr($v['proGrade'],'2')){
                            array_push($showlevels,$le2);
                        }
                        if(strstr($v['proGrade'],'3')){
                            array_push($showlevels,$le3);
                        }
                        $showlevels = implode(',',$showlevels);
                        pdo_update("ewei_shop_goods", ['showlevels'=>$showlevels,'buylevels'=>$showlevels], array( "goodsisn" => $v["inCode"], "uniacid" => $this->uniacid, 'merchid'=>$this->merchid));
                    }


                }
                $result = ['status'=>'success','msg'=>'商品浏览等级'];

            }else{
                $result = ['status'=>'error','msg'=>'接收不到参数'];
            }
            echo json_encode($result);
            die;

        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }


    /*获取erp商品参数
     * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.info
     * goodsBatch 参数
     */
    public function info()
    {
        try {
            global $_W;
            global $_GPC;

            $input = file_get_contents("php://input");
            $data = json_decode($input,true);

            if(isset($data['goodsBatch']) && !empty($data['goodsBatch'])){
                foreach($data['goodsBatch'] as $k=>$v){
                    $goods = pdo_fetch('select id,goodsisn from '.tablename('ewei_shop_goods')." where goodsisn = :goodsisn and uniacid = :uniacid and merchid = :merchid",array(':goodsisn'=>$v["inCode"],':uniacid'=>$this->uniacid,':merchid'=>$this->merchid));
                    //查询参数表

                    //批号
                    if($v['batchNum'] != ''){
                        $batchNum = pdo_fetch('select id,title from '.tablename('ewei_shop_goods_param')." where goodsid = :goodsid and uniacid = :uniacid and title like :title",array(':goodsid'=>$goods['id'],':uniacid'=>$this->uniacid,':title'=>'批号'));
                        if(empty($batchNum)){
                            //还没有批号  添加
                            $bat = [
                                'uniacid'=>$this->uniacid,
                                'goodsid'=>$goods['id'],
                                'title'=>'批号',
                                'value'=>$v['batchNum']
                            ];
                            pdo_insert('ewei_shop_goods_param', $bat);
                        }else{
                            //有批号 修改
                            $bat = [
                                'title'=>'批号',
                                'value'=>$v['batchNum']
                            ];
                            pdo_update('ewei_shop_goods_param', $bat, array('id' => $batchNum['id']));
                        }
                    }


                    //有效期
                    if($v['validity'] != ''){
                        $validity = pdo_fetch('select id,title from '.tablename('ewei_shop_goods_param')." where goodsid = :goodsid and uniacid = :uniacid and title like :title",array(':goodsid'=>$goods['id'],':uniacid'=>$this->uniacid,':title'=>'有效期'));
                        if(empty($validity)){
                            //还没有有效期  添加
                            $va = [
                                'uniacid'=>$this->uniacid,
                                'goodsid'=>$goods['id'],
                                'title'=>'有效期',
                                'value'=>$v['validity']
                            ];
                            pdo_insert('ewei_shop_goods_param', $va);
                        }else{
                            //有有效期 修改
                            $va = [
                                'title'=>'有效期',
                                'value'=>$v['validity']
                            ];
                            pdo_update('ewei_shop_goods_param', $va, array('id' => $validity['id']));
                        }
                    }


                    //生产日期
                    if($v['prodDate'] != ''){
                        $prodDate = pdo_fetch('select id,title from '.tablename('ewei_shop_goods_param')." where goodsid = :goodsid and uniacid = :uniacid and title like :title",array(':goodsid'=>$goods['id'],':uniacid'=>$this->uniacid,':title'=>'生产日期'));
                        if(empty($prodDate)){
                            //还没有生产日期  添加
                            $pro = [
                                'uniacid'=>$this->uniacid,
                                'goodsid'=>$goods['id'],
                                'title'=>'生产日期',
                                'value'=>$v['prodDate']
                            ];
                            pdo_insert('ewei_shop_goods_param', $pro);
                        }else{
                            //有生产日期 修改
                            $pro = [
                                'title'=>'生产日期',
                                'value'=>$v['prodDate']
                            ];
                            pdo_update('ewei_shop_goods_param', $pro, array('id' => $prodDate['id']));
                        }
                    }


                }
                $result = ['status'=>'success','msg'=>'批号更新成功'];
            }else{
                $result = ['status'=>'error','msg'=>'接收不到参数'];
            }
            echo json_encode($result);
            die;
        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }

    /*获取erp商品资料
     * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.goods
     * goodsInforma 参数
     */
    public function goodsInfo()
    {
        try {
            global $_W;
            global $_GPC;
            $input = file_get_contents("php://input");
            $data = json_decode($input,true);

            $msg = 'date:' . date('Y-m-d H:i:s') . '  ' . json_encode($data['goodsInforma']);
            $dir = __DIR__ . '/logs';
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $file = $dir . '/' . date('Y-m-d') . 'goods.log.txt';
            $lin = file_put_contents($file, $msg . PHP_EOL, FILE_APPEND);

            echo 123456;
            die;

        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }


    /*提交订单到erp
    * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.shop_order
    * getOrder 参数
    */
    public function shop_order()
    {
        try {
            global $_W;
            global $_GPC;
            $input = file_get_contents("php://input");
            $data = json_decode($input,true);
            if($data['getOrder'] == '订单下载'){

                $condition = " o.uniacid = {$this->uniacid} and o.erp_status=0 and o.ismr=0 and o.deleted=0 and o.isparent=0 and o.istrade=0 ";
                $condition .= "and ((o.status >=0 and o.paytype != 0) or (o.status=0 and o.paytype=0 and o.btype=2))";

                $orderbuy='o.createtime';
                $sql = "select o.id as oId,o.ordersn as orderId,o.openid as customerId,o.price as totalCost,o.status,o.remark,o.dispatchtype as distributionMode,
                      o.expresscom,o.expresssn as Order_kdd,o.express,o.createtime as orderTime,o.merchid,o.refundid,o.btype,
                      a.realname as name,a.mobile as phone,a.province as aprovince ,a.city as acity , a.area as aarea, a.address as aaddress,
                      r.rtype,r.status as rstatus,
                      m.company as drugstoreName,m.invoice_type as invoiceType,m.company_address as cus_address,m.erp_user_id,m.delivery as senType from "
                    . tablename('ewei_shop_order') . " o"
                    . " left join " . tablename('ewei_shop_order_refund') . " r on r.id =o.refundid "
                    . " left join " . tablename('ewei_shop_member') . " m on m.openid=o.openid and m.uniacid =  o.uniacid "
                    . " left join " . tablename('ewei_shop_member_address') . " a on a.id=o.addressid "
                    . "  where {$condition}  ORDER BY {$orderbuy} DESC  ";

                $list = pdo_fetchall($sql);
                if(!empty($list)){
                    foreach($list as $k=>$v){
                        if($v['btype']==2){
                            if(!empty($v['remark'])){
                                $v['remark'] = $v['remark'].'，周期付款订单（未付款）。';
                            }else{
                                $v['remark'] = '周期付款订单（未付款）。';
                            }
                        }
                        //订单时间
                        $list[$k]['orderTime'] = date('Y-m-d H:i',$v['orderTime']);
                        //多商户 商户名
                        if($v['merchid'] == 0){
                            $list[$k]['merchname'] = '集和堂医药';
                        }else{
                            $list[$k]['merchname'] = pdo_getcolumn('ewei_shop_merch_user', array('id' => $v['merchid']), 'merchname');
                        }
                        //订单商品
                        $condition1 = " o.uniacid = {$this->uniacid} and o.orderid = {$v['oId']}";
                        $sql1 = "select o.id as serialNo, o.price as Amount,o.total as Number,o.isexport,o.export_num as outboundNum,o.merchid,g.goodsisn as inCode,g.goodssn as drugCode,g.marketprice from "
                            . tablename('ewei_shop_order_goods') . " o"
                            . " left join " . tablename('ewei_shop_goods') . " g on g.id =o.goodsid "
                            . "  where {$condition1}";
                        $list[$k]['order_goods'] = pdo_fetchall($sql1);
                        if(!empty($list[$k]['order_goods'])){
                            foreach($list[$k]['order_goods'] as $k1=>$v1){
                                $list[$k]['order_goods'][$k1]['price'] = number_format(($v1['Amount']/$v1['Number']),2);
                                $list[$k]['order_goods'][$k1]['oId'] = $v['oId'];
                                $list[$k]['order_goods'][$k1]['orderId'] = $v['orderId'];
                                $list[$k]['order_goods'][$k1]['expresscom'] = '';
                                $list[$k]['order_goods'][$k1]['expresssn'] = '';
                                $list[$k]['order_goods'][$k1]['express'] = '';

                                //多商户 商户名
                                if($v1['merchid'] == 0){
                                    $list[$k]['order_goods'][$k1]['merchname'] = '集和堂医药';
                                }else{
                                    $list[$k]['order_goods'][$k1]['merchname'] = pdo_getcolumn('ewei_shop_merch_user', array('id' => $v1['merchid']), 'merchname');
                                }

                            }
                        }


                        //修改当前订单为已提交erp
                       pdo_update('ewei_shop_order', ['erp_status'=>1], array('id' => $v['oId']));

                    }
                }
                echo json_encode($list);
                die;
            }else{
                echo json_encode(['status'=>'error','msg'=>'接收不到参数']);
                die;
            }

        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }


    /*erp订单发货
    * https://jkxiaoge.com/app/index.php?i=8&c=entry&m=ewei_shopv2&do=mobile&r=goods.kucun.send
    * OrderStatus 参数
    */
    public function send()
    {
        try {
            global $_W;
            global $_GPC;
            $input = file_get_contents("php://input");
            $data = json_decode($input,true);

            if(isset($data['OrderStatus']) && !empty($data['OrderStatus'])){
                foreach($data['OrderStatus'] as $k=>$v){
                    //先查询订单信息
                    $os = pdo_fetch("SELECT id,status,btype FROM ".tablename('ewei_shop_order')." WHERE id = :id and ordersn = :ordersn", array(':id' => $v['oId'],':ordersn'=>$v['orderId']));
                    //是否待发货  已付款或者周期付款（未付款）
                    if(($os['status']==1 && $os['btype']!=2) || ($os['status']==0 && $os['btype']==2)){
                        if($v['status'] == 3){
                            //已发货 修改订单商品状态
                            $gg = [
                                'isexport'=>1,
                                'export_num'=>$v['outBoundNum'],
                                'erp_trade_no'=>$v['outboundOrder'],
                            ];
                            pdo_update('ewei_shop_order_goods', $gg, array('id' => $v['serialNo'],'orderid'=>$v['oId']));
                        }
                    }

                }

                foreach($data['OrderStatus'] as $k=>$v){
                    //先查询订单信息
                    $os = pdo_fetch("SELECT id,status,btype FROM ".tablename('ewei_shop_order')." WHERE id = :id and ordersn = :ordersn", array(':id' => $v['oId'],':ordersn'=>$v['orderId']));
                    //是否待发货 已付款的改变状态 周期付款（status=0 && btype=2）不改变状态

                    //if(($os['status']==1 && $os['btype']!=2) || ($os['status']==0 && $os['btype']==2)){
                    if($os['status']==1){
                        if($v['status'] == 3){
                            //已发货 修改订单状态
                            //修改当前订单状态
                            pdo_update('ewei_shop_order', ['status'=>2,'ischeap'=>0,'sendtime'=>time()], array('id' => $v['oId'],'ordersn'=>$v['orderId']));
                        }else if($v['status'] == 2){
                            //拣货中
                            pdo_update('ewei_shop_order', ['ischeap'=>1], array('id' => $v['oId'],'ordersn'=>$v['orderId']));
                        }
                    }

                }

                echo json_encode(['status'=>'success','msg'=>'更新成功']);
                die();
                
                //把退款的数据传回erp
//                $condition = " uniacid = {$this->uniacid} and erp_status=1 and ismr=0 and deleted=0 and isparent=0 and istrade=0 ";
//                $condition .= "and status=-1 and refundid!=0";
//                $orderbuy='createtime';
//                $sql = "select id as oId,ordersn as orderId,status,refundid,ispartrefund from "
//                    . tablename('ewei_shop_order')
//                    . "  where {$condition}  ORDER BY {$orderbuy} DESC  ";
//                $list = pdo_fetchall($sql);
//                if($list){
//                    foreach($list as $kk=>$vv){
//                        if($vv['ispartrefund'] == 1){
//                            //整单退
//                            $list[$kk]['refund_types'] = '整单退';
//                            $list[$kk]['order_goods'] = [];
//                        }else{
//                            $list[$kk]['refund_types'] = '单品退';
//                            $ts = pdo_fetch("SELECT goodsid as serialNo, refundnum FROM ".tablename('ewei_shop_order_refund')." WHERE id = :id LIMIT 1", array(':id' => $vv['refundid']));
//                            $list[$kk]['order_goods'] = $ts;
//                        }
//                    }
//                    echo json_encode($list);
//                    die;
//
//                }else{
//                    echo json_encode($list='');
//                    die;
//                }

            }else{
                echo json_encode(['status'=>'error','msg'=>'接收不到参数']);
                die;
            }


        } catch (Exception $e) {
            echo json_encode(['status'=>'error','msg'=>'请求失败']);
            die;
        }
    }





    function edit(){
        global $_W;
        global $_GPC;

        //$msg = 'date:' . date('Y-m-d H:i:s') . '  ' . json_encode($data['OrderStatus']);
//        $dir = __DIR__ . '/logs';
//        if (! is_dir($dir)) {
//            mkdir($dir, 0777, true);
//        }
//        $file = $dir . '/' . date('Y-m-d') . 's.log.txt';
//        file_put_contents($file, $msg . PHP_EOL, FILE_APPEND);

        $data = [
            "uniacid"=> "8",
            "uid"=> "0",
            "groupid"=> "",
            "level"=> "0",
            "agentid"=> "0",
            "openid"=> "wap_user_8_15173301602",
            "realname"=> "",
            "mobile"=> "15173301602",
            "pwd"=> "c8fe850de6ee091f7e2a26f9c6ab4adb",
            "weixin"=> "",
            "content"=> "",
            "createtime"=> "1557452667",
            "agenttime"=> "0",
            "status"=> "0",
            "isagent"=> "0",
            "clickcount"=> "0",
            "agentlevel"=> "0",
            "noticeset"=> NULL,
            "nickname"=> "151xxxx1602",
            "credit1"=> "0.00",
            "credit2"=> "0.00",
            "diymaxcredit"=> "0",
            "maxcredit"=> "0",
            "birthyear"=> "",
            "birthmonth"=> "",
            "birthday"=> "",
            "gender"=> "0",
            "avatar"=> "",
            "province"=> "",
            "city"=> "",
            "area"=> "",
            "childtime"=> "0",
            "agentnotupgrade"=> "0",
            "inviter"=> "0",
            "agentselectgoods"=> "0",
            "agentblack"=> "0",
            "username"=> "",
            "fixagentid"=> "0",
            "diymemberid"=> "0",
            "diymemberdataid"=> "0",
            "diymemberdata"=> NULL,
            "diycommissionid"=> "0",
            "diycommissiondataid"=> "0",
            "diycommissiondata"=> NULL,
            "isblack"=> "0",
            "diymemberfields"=> NULL,
            "diycommissionfields"=> NULL,
            "commission_total"=> "0.00",
            "endtime2"=> "0",
            "ispartner"=> "0",
            "partnertime"=> "0",
            "partnerstatus"=> "0",
            "partnerblack"=> "0",
            "partnerlevel"=> "0",
            "partnernotupgrade"=> "0",
            "diyglobonusid"=> "0",
            "diyglobonusdata"=> NULL,
            "diyglobonusfields"=> NULL,
            "isaagent"=> "0",
            "aagentlevel"=> "0",
            "aagenttime"=> "0",
            "aagentstatus"=> "0",
            "aagentblack"=> "0",
            "aagentnotupgrade"=> "0",
            "diyaagentid"=> "0",
            "diyaagentdata"=> NULL,
            "diyaagentfields"=> NULL,
            "aagenttype"=> "0",
            "aagentprovinces"=> NULL,
            "aagentcitys"=> NULL,
            "aagentareas"=> NULL,
            "salt"=> "kxuuwR7f977pN4BB",
            "mobileverify"=> "1",
            "mobileuser"=> "0",
            "carrier_mobile"=> "0",
            "isauthor"=> "0",
            "authortime"=> "0",
            "authorstatus"=> "0",
            "authorblack"=> "0",
            "authorlevel"=> "0",
            "authornotupgrade"=> "0",
            "diyauthorid"=> "0",
            "diyauthordata"=> NULL,
            "diyauthorfields"=> NULL,
            "authorid"=> "0",
            "comefrom"=> NULL,
            "openid_qq"=> NULL,
            "openid_wx"=> NULL,
            "datavalue"=> "",
            "openid_wa"=> NULL,
            "nickname_wechat"=> "",
            "avatar_wechat"=> "",
            "updateaddress"=> "1",
            "membercardid"=> "",
            "membercardcode"=> "",
            "membershipnumber"=> "",
            "membercardactive"=> "0",
            "commission"=> "0.00",
            "commission_pay"=> "0.00",
            "idnumber"=> NULL,
            "wxcardupdatetime"=> "1558318911",
            "hasnewcoupon"=> "0",
            "isheads"=> "0",
            "headsstatus"=> "0",
            "headstime"=> "0",
            "headsid"=> "0",
            "diyheadsid"=> "0",
            "diyheadsdata"=> NULL,
            "diyheadsfields"=> NULL,
            "applyagenttime"=> "0",
            "company"=> "测试账号",
            "contact_name"=> "测试账号",
            "contact_phone"=> "15173301602",
            "company_address"=> "广东省 广州市 天河区",
            "company_areas"=> "黄埔大道中280号员村乐雅苑集和堂",
            "invoice_type"=> "1",
            "yao_card"=> "10081",
            "yao_card_time"=> "1660147200",
            "gsp_card"=> "10082",
            "gsp_card_time"=> "1634400000",
            "business_card"=> "10086",
            "business_img"=> "images/6/2019/04/H2AjZvE243AkKf6793FeMEAYy6fevL.jpg",
            "is_shen"=> "1",
            "yao_card_img"=> "images/6/2019/04/VS3434Y7Py1W1E4e5D1o3Z17eP1eee.jpg",
            "erp_user_id"=> "",
        ];


//        pdo_insert('ewei_shop_member', $data);
//        $uid = pdo_insertid();
//        dump($uid);
//        die;


    }

    function demo()
    {

//        $sql = 'SELECT id,title,goodsisn,goodssn FROM ' . tablename('ewei_shop_goods') . 'where (thumb=\'\' or thumb=\'images/8/2019/05/JCoCABcc9aZ62OIC6azcC7CIC6eCKa.jpg\') and status=1';
//        $data = pdo_fetchall($sql);
//        foreach($data as $kk=>$vv){
//            $data[$kk]['cj'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $vv['id'],'title'=>'生产厂家'), 'value');
//            $data[$kk]['gg'] = pdo_getcolumn('ewei_shop_goods_param', array('goodsid' => $vv['id'],'title'=>'规格'), 'value');
//        }
//
//
//        $string="";
//        foreach ($data as $key => $value)
//        {
//            foreach ($value as $k => $val)
//            {
//                $value[$k]=iconv('utf-8','gb2312',$value[$k]);
//            }
//
//            $string .= implode(",",$value)."\n"; //用英文逗号分开
//        }
//        $filename = date('Ymd').'.csv'; //设置文件名
//        header("Content-type:text/csv");
//        header("Content-Disposition:attachment;filename=".$filename);
//        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
//        header('Expires:0');
//        header('Pragma:public');
//        echo $string;


//        $filePath = __DIR__ . "/cate.xls";
//        require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';
//        require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel/IOFactory.php';
//
//        $reader = new PHPExcel_Reader_Excel5();
//        $PHPExcel = $reader->load($filePath);
//        $sheet = $PHPExcel->getSheet(0);
//        $highestRow = $sheet->getHighestRow();
//        $highestColumm = $sheet->getHighestColumn();
//        $highestColumm= PHPExcel_Cell::columnIndexFromString($highestColumm);
//
//        /** 循环读取每个单元格的数据 */
//        for ($row = 1; $row <= $highestRow; $row++){
//            for ($column = 0; $column < $highestColumm; $column++) {
//                $columnName = PHPExcel_Cell::stringFromColumnIndex($column);
//                $excelarr[$row][] =  $sheet->getCellByColumnAndRow($column, $row)->getValue();
//            }
//        }
//
//        foreach($excelarr as $k=>$v){
//            $os = [
//                'deleted'=>0,
//            ];
//            $result = pdo_update('ewei_shop_goods', $os, array('uniacid' => 8,'goodsisn'=>$v[0]));
//
//        }
//
//        echo '更新成功';


    }

}