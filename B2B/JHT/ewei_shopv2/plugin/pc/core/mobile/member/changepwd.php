<?php
if (!(defined("IN_IA")))
{
    exit("Access Denied");
}
require EWEI_SHOPV2_PLUGIN . "pc/core/page_login_mobile.php";
class Changepwd_EweiShopV2Page extends PcMobileLoginPage
{
    protected $member;

    public function __construct()
    {
        global $_W;
        global $_GPC;
        parent::__construct();
        $m = m('member')->getInfo($_W['openid']);
        if ($m['level'] == 0) {
            $m['level_name'] = '普通会员';
        } else {
            $m['level_name'] = pdo_getcolumn('ewei_shop_member_level', array('id' => $m['level']), 'levelname');
        }
        $this->member = $m;
    }

    public function main()
    {
        global $_W;
        global $_GPC;
        $returnurl = urldecode(trim($_GPC['returnurl']));
        $member = $this->member;
        $wapset = m('common')->getSysset('wap');
        $ice_menu_array = array( array('menu_key' => 'index', 'menu_name' => '修改密码', 'menu_url' => mobileUrl('pc.member.company')) );
        $nav_link_list = array( array('link' => mobileUrl('pc'), 'title' => '首页'), array('link' => mobileUrl('pc.member'), 'title' => '我的商城'), array('title' => '修改密码') );

//        if (is_weixin() || empty($_GPC['__ewei_shopv2_member_session_' . $_W['uniacid']])) {
//            header('location: ' . mobileUrl());
//        }

        if ($_W['ispost']) {
            $mobile = trim($_GPC['mobile']);
            $verifycode = trim($_GPC['verifycode']);
            $pwd = trim($_GPC['pwd']);
            if (empty($mobile))
            {
                show_json(0, '请输入正确的手机号');
            }
            if (empty($verifycode))
            {
                show_json(0, '请输入验证码');
            }
            if (empty($pwd))
            {
                show_json(0, '请输入密码');
            }
            $key = '__ewei_shop_member_verifycodesession_' . $_W['uniacid'] . '_' . $mobile;
            if (!(isset($_SESSION[$key])) || ($_SESSION[$key] !== $verifycode) || !(isset($_SESSION['verifycodesendtime'])) || (($_SESSION['verifycodesendtime'] + 600) < time()))
            {
                show_json(0, '验证码错误或已过期!');
            }
            

            $member = pdo_fetch('select id,openid,mobile,pwd,salt,credit1,credit2, createtime from ' . tablename('ewei_shop_member') . ' where mobile=:mobile and uniacid=:uniacid and mobileverify=1 limit 1', array(':mobile' => $mobile, ':uniacid' => $_W['uniacid']));
            $salt = empty($member) ? '' : $member['salt'];

            if (empty($salt)) {
                $salt = random(16);

                while (1) {
                    $count = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_member') . ' where salt=:salt limit 1', array(':salt' => $salt));

                    if ($count <= 0) {
                        break;
                    }

                    $salt = random(16);
                }
            }

            pdo_update('ewei_shop_member', array('mobile' => $mobile, 'pwd' => md5($pwd . $salt), 'salt' => $salt, 'mobileverify' => 1), array('id' => $this->member['id'], 'uniacid' => $_W['uniacid']));
            unset($_SESSION[$key]);
            $member = pdo_fetch('select id,openid,mobile,pwd,salt,company,business_card,business_img from ' . tablename('ewei_shop_member') . ' where mobile=:mobile and mobileverify=1 and uniacid=:uniacid limit 1', array(':mobile' => $mobile, ':uniacid' => $_W['uniacid']));
            m('account')->setLogin($member);
            show_json(1);
        }

        $sendtime = $_SESSION['verifycodesendtime'];
        if (empty($sendtime) || $sendtime + 60 < time()) {
            $endtime = 0;
        }
        else {
            $endtime = 60 - (time() - $sendtime);
        }

        include $this->template();
    }


}