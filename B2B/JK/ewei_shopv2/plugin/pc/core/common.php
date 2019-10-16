<?php
function fenye($total, $pageIndex, $pageSize = 15, $url = '', $context = array('before' => 5, 'after' => 4, 'ajaxcallback' => '')) 
{
	global $_W;
	$pdata = array('tcount' => 0, 'tpage' => 0, 'cindex' => 0, 'findex' => 0, 'pindex' => 0, 'nindex' => 0, 'lindex' => 0, 'options' => '');
	if ($context['ajaxcallback']) 
	{
		$context['isajax'] = true;
	}
	$pdata['tcount'] = $total;
	$pdata['tpage'] = ceil($total / $pageSize);
	if ($pdata['tpage'] <= 1) 
	{
		return '';
	}
	$cindex = $pageIndex;
	$cindex = min($cindex, $pdata['tpage']);
	$cindex = max($cindex, 1);
	$pdata['cindex'] = $cindex;
	$pdata['findex'] = 1;
	$pdata['pindex'] = ((1 < $cindex ? $cindex - 1 : 1));
	$pdata['nindex'] = (($cindex < $pdata['tpage'] ? $cindex + 1 : $pdata['tpage']));
	$pdata['lindex'] = $pdata['tpage'];
	if ($context['isajax']) 
	{
		if (!($url)) 
		{
			$url = $_W['script_name'] . '?' . http_build_query($_GET);
		}
		$pdata['faa'] = 'href="javascript:;" page="' . $pdata['findex'] . '" ' . (($callbackfunc ? 'onclick="' . $callbackfunc . '(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['findex'] . '\', this);return false;"' : ''));
		$pdata['paa'] = 'href="javascript:;" page="' . $pdata['pindex'] . '" ' . (($callbackfunc ? 'onclick="' . $callbackfunc . '(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['pindex'] . '\', this);return false;"' : ''));
		$pdata['naa'] = 'href="javascript:;" page="' . $pdata['nindex'] . '" ' . (($callbackfunc ? 'onclick="' . $callbackfunc . '(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['nindex'] . '\', this);return false;"' : ''));
		$pdata['laa'] = 'href="javascript:;" page="' . $pdata['lindex'] . '" ' . (($callbackfunc ? 'onclick="' . $callbackfunc . '(\'' . $_W['script_name'] . $url . '\', \'' . $pdata['lindex'] . '\', this);return false;"' : ''));
	}
	else if ($url) 
	{
		$pdata['faa'] = 'href="?' . str_replace('*', $pdata['findex'], $url) . '"';
		$pdata['paa'] = 'href="?' . str_replace('*', $pdata['pindex'], $url) . '"';
		$pdata['naa'] = 'href="?' . str_replace('*', $pdata['nindex'], $url) . '"';
		$pdata['laa'] = 'href="?' . str_replace('*', $pdata['lindex'], $url) . '"';
	}
	else 
	{
		$_GET['page'] = $pdata['findex'];
		$pdata['faa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
		$_GET['page'] = $pdata['pindex'];
		$pdata['paa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
		$_GET['page'] = $pdata['nindex'];
		$pdata['naa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
		$_GET['page'] = $pdata['lindex'];
		$pdata['laa'] = 'href="' . $_W['script_name'] . '?' . http_build_query($_GET) . '"';
	}
	$html = '<div class="pagination" style="margin:0 auto;width: 100%"><ul>';
	if (1 < $pdata['cindex']) 
	{
		$html .= '<li><a ' . $pdata['faa'] . ' class="demo"><span>首页</span></a></li>';
		$html .= '<li><a ' . $pdata['paa'] . ' class="demo"><span>&laquo;上一页</span></a></li>';
	}
	if (!($context['before']) && ($context['before'] != 0)) 
	{
		$context['before'] = 5;
	}
	if (!($context['after']) && ($context['after'] != 0)) 
	{
		$context['after'] = 4;
	}
	if (($context['after'] != 0) && ($context['before'] != 0)) 
	{
		$range = array();
		$range['start'] = max(1, $pdata['cindex'] - $context['before']);
		$range['end'] = min($pdata['tpage'], $pdata['cindex'] + $context['after']);
		if (($range['end'] - $range['start']) < ($context['before'] + $context['after'])) 
		{
			$range['end'] = min($pdata['tpage'], $range['start'] + $context['before'] + $context['after']);
			$range['start'] = max(1, $range['end'] - $context['before'] - $context['after']);
		}
		$i = $range['start'];
		while ($i <= $range['end']) 
		{
			if ($context['isajax']) 
			{
				$aa = 'href="javascript:;" page="' . $i . '" ' . (($callbackfunc ? 'onclick="' . $callbackfunc . '(\'' . $_W['script_name'] . $url . '\', \'' . $i . '\', this);return false;"' : ''));
			}
			else if ($url) 
			{
				$aa = 'href="?' . str_replace('*', $i, $url) . '"';
			}
			else 
			{
				$_GET['page'] = $i;
				$aa = 'href="?' . http_build_query($_GET) . '"';
			}
			$html .= (($i == $pdata['cindex'] ? '<li ><a href="javascript:;"><span class="currentpage">' . $i . '</span></a></li>' : '<li><a ' . $aa . '><span >' . $i . '</span></a></li>'));
			++$i;
		}
	}
	if ($pdata['cindex'] < $pdata['tpage']) 
	{
		$html .= '<li><a ' . $pdata['naa'] . ' class="demo"><span>下一页&raquo;</span></a></li>';
		$html .= '<li><a ' . $pdata['laa'] . ' class="demo"><span>尾页</span></a></li>';
	}
	$html .= '</ul></div>';
	return $html;
}
function check_login() 
{
	global $_W;
	if (!(empty($_W['openid']))) 
	{
		$member = m('member')->getMember($_W['openid']);
        $_SESSION['wechat_openid'] = $member['openid'];
		return $member;
	}
	return false;
}
function index_cate() 
{
	global $_W;
	$category_set = $_W['shopset']['category'];
	if ($category_set['level'] == -1) 
	{
		return array();
	}
	$level = intval($category_set['level']);
	$category = m('shop')->getCategory();
	$category_parent = array();
	$category_children = array();
	$category_grandchildren = array();
	foreach ($category['parent'] as $value ) 
	{
		if ($value['enabled'] == 1) 
		{
			$category_parent[$value['parentid']][] = $value;
			if (!(empty($category['children'][$value['id']])) && (2 <= $level)) 
			{
				foreach ($category['children'][$value['id']] as $val ) 
				{
					if ($val['enabled'] == 1) 
					{
						$category_children[$val['parentid']][] = $val;
						if (!(empty($category['children'][$val['id']])) && (3 <= $level)) 
						{
							foreach ($category['children'][$val['id']] as $v ) 
							{
								if ($v['enabled'] == 1) 
								{
									$category_grandchildren[$v['parentid']][] = $v;
								}
							}
						}
					}
				}
			}
		}
	}
	return array("parent" => $category_parent, 'children' => $category_children, 'grandchildren' => $category_grandchildren);
}
function get_menus($type = 0) 
{
	global $_W,$_GPC;
	$openid = $_W['openid'];
	$list = pdo_fetchall('SELECT title,link FROM ' . tablename('ewei_shop_pc_menu') . ' WHERE type=' . $type . ' AND enabled=1 AND uniacid=' . $_W['uniacid'] . ' ORDER BY displayorder DESC,id ASC');
	//20190729 更改顶部菜单显示
	$sel = pdo_fetch('SELECT groupid FROM ' . tablename('ewei_shop_member') . ' WHERE uniacid=:uniacid AND openid=:openid', array(':uniacid' => $_W['uniacid'],':openid' => $openid));
	$del = pdo_fetchall('SELECT groupname FROM ' . tablename('ewei_shop_member_group') . ' WHERE id in (' . $sel['groupid'] .')');
	$arr = array_column($del,'groupname');
	$group = 1;
//	if(in_array("战略合作",$arr)){
//		$group = 1;
//	}

	if ($type == 0) 
	{
		if ($list) 
		{
			foreach ($list as $k=>$menu )
			{
				if($menu['title'] == '自营专区'){
					if($group == 0){
                        continue;
					}
				}
				if($_GPC['s']==$k){
                    echo '<li><a style="color:#F87622" href=\'' . $menu['link'] . '&k='.$k.'\'>' . $menu['title'] . '</a></li>' . "\n";
                }else{
                    echo '<li><a href=\'' . $menu['link'] . '&s='.$k.'\'>' . $menu['title'] . '</a></li>' . "\n";
                }

			}
		}
		else 
		{
			echo "<li><a href='" . mobileUrl("pc") . "'>商城首页</a></li>
";
		}
	}
	if ($type == 1) 
	{
		if ($list) 
		{
			foreach ($list as $menu ) 
			{
				echo '<a href=\'' . $menu['link'] . '\'>' . $menu['title'] . '</a> | ' . "\n";
			}
		}
	}
	if ($type == 2) 
	{
		if ($list) 
		{
			foreach ($list as $menu ) 
			{
				echo '<li><a href=\'' . $menu['link'] . '\'>' . $menu['title'] . '</a></li>' . "\n";
			}
		}
	}
}
function show_adv($advname, $width, $height, $target = NULL) 
{
	global $_W;
	$adv = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_pc_adv') . ' WHERE advname=:advname AND width=:width AND height=:height AND uniacid=:uniacid', array(':advname' => $advname, ':width' => $width, ':height' => $height, ':uniacid' => $_W['uniacid']));
	if (empty($adv)) 
	{
		pdo_insert('ewei_shop_pc_adv', array('advname' => $advname, 'src' => '', 'link' => '', 'width' => $width, 'height' => $height, 'uniacid' => $_W['uniacid'], 'enabled' => 0));
		return;
	}
	if (intval($adv['enabled'])) 
	{
		$title = ((empty($adv['title']) ? '' : 'title=\'' . trim($adv['title']) . '\''));
		$link = ((empty($adv['link']) ? '' : trim($adv['link'])));
		$width = ((empty($adv['width']) ? '' : 'width:' . intval($adv['width']) . 'px;'));
		$height = ((empty($adv['height']) ? '' : 'height:' . intval($adv['height']) . 'px;'));
		$src = ((empty($adv['src']) ? '' : tomedia(trim($adv['src']))));
		$alt = ((empty($adv['alt']) ? '' : trim($adv['alt'])));
		$target = ((empty($target) ? '' : 'target=\'_blank\''));
		echo '<a href=\'' . $link . '\' ' . $target . ' ' . $title . '><img style=\'' . $width . $height . '\' border=\'0\' src=\'' . $src . '\' alt=\'' . $alt . '\'/></a>';
	}
}
function show_copyright() 
{
	global $_W;
	$data = m('common')->getSysset('shop');
	$copyright = htmlspecialchars_decode($data['pc_copyright']);
	echo $copyright;
}
function get_qa() 
{
	global $_W;
	$qa_categories = pdo_fetchall('SELECT id,name FROM ' . tablename('ewei_shop_qa_category') . ' WHERE uniacid = \'' . $_W['uniacid'] . '\' ORDER BY displayorder DESC LIMIT 4');
	foreach ($qa_categories as &$qa_category ) 
	{
		$qa_category['list'] = pdo_fetchall('SELECT id,title FROM ' . tablename('ewei_shop_qa_question') . ' where  cate=:cate_id AND uniacid=:uniacid AND status=1 ORDER BY displayorder DESC,id DESC LIMIT 5', array(':cate_id' => $qa_category['id'], ':uniacid' => $_W['uniacid']));
	}
	unset($qa_category);
	return $qa_categories;
}
function get_set() 
{
	global $_W;
	$data = m('common')->getSysset('shop');
	return $data;
}
function get_links() 
{
	global $_W;
	$links = pdo_fetchall('SELECT linkname,url FROM ' . tablename('ewei_shop_pc_link') . ' WHERE  uniacid=:uniacid AND status=1 ORDER BY displayorder', array(':uniacid' => $_W['uniacid']));
	return $links;
}
function truncateCn($string, $length = 80, $etc = '...', $charset = 'utf-8') 
{
	if ($length == 0) 
	{
		return '';
	}
	$string = strip_tags($string);
	$string = str_replace(array(' ', '　', "\t", "\n", "\r"), array('', '', '', '', ''), $string);
	if (function_exists("mb_substr")) 
	{
		$etc = (($length < mb_strlen($string, $charset) ? $etc : ''));
		return mb_substr($string, 0, $length, $charset) . $etc;
	}
	if ($charset == 'utf-8') 
	{
		$pa = '/[' . "\x1" . '-]|[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']|' . "?" . '[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']|[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']|' . "?" . '[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']|[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']/';
	}
	else 
	{
		$pa = '/[' . "\x1" . '-]|[' . "?" . '-' . "?" . '][' . "?" . '-' . "?" . ']/';
	}
	preg_match_all($pa, $string, $t_string);
	if ($length < count($t_string[0])) 
	{
		return join('', array_slice($t_string[0], 0, $length)) . $etc;
	}
	return join('', array_slice($t_string[0], 0, $length));
}



/**
 * 替换fckedit中的图片 添加域名
 * @param  string $content 要替换的内容
 * @param  string $strUrl 内容中图片要加的域名
 * @return string
 * @eg
 */
function replacePicUrl($content = null, $strUrl = null) {
    if ($strUrl) {
        //提取图片路径的src的正则表达式 并把结果存入$matches中
        preg_match_all("/<img(.*)src=\"([^\"]+)\"[^>]+>/isU",$content,$matches);
        $img = "";
        if(!empty($matches)) {
            //注意，上面的正则表达式说明src的值是放在数组的第三个中
            $img = $matches[2];
        }else {
            $img = "";
        }
        if (!empty($img)) {
            $patterns= array();
            $replacements = array();
            foreach($img as $imgItem){
                $final_imgUrl = $strUrl.$imgItem;
                $replacements[] = $final_imgUrl;
                $img_new = "/".preg_replace("/\//i","\/",$imgItem)."/";
                $patterns[] = $img_new;
            }

            //让数组按照key来排序
            ksort($patterns);
            ksort($replacements);

            //替换内容
            $vote_content = preg_replace($patterns, $replacements, $content);

            return $vote_content;
        }else {
            return $content;
        }
    } else {
        return $content;
    }
}



?>