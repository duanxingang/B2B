<?php
if (!(defined('IN_IA'))) 
{
	exit('Access Denied');
}
class Excel_EweiShopV2Model 
{
	protected function column_str($key) 
	{
		$array = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV', 'BW', 'BX', 'BY', 'BZ', 'CA', 'CB', 'CC', 'CD', 'CE', 'CF', 'CG', 'CH', 'CI', 'CJ', 'CK', 'CL', 'CM', 'CN', 'CO', 'CP', 'CQ', 'CR', 'CS', 'CT', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DA', 'DB', 'DC', 'DD', 'DE', 'DF', 'DG', 'DH', 'DI', 'DJ', 'DK', 'DL', 'DM', 'DN', 'DO', 'DP', 'DQ', 'DR', 'DS', 'DT', 'DU', 'DV', 'DW', 'DX', 'DY', 'DZ', 'EA', 'EB', 'EC', 'ED', 'EE', 'EF', 'EG', 'EH', 'EI', 'EJ', 'EK', 'EL', 'EM', 'EN', 'EO', 'EP', 'EQ', 'ER', 'ES', 'ET', 'EU', 'EV', 'EW', 'EX', 'EY', 'EZ');
		return $array[$key];
	}
	protected function column($key, $columnnum = 1) 
	{
		return $this->column_str($key) . $columnnum;
	}
	public function export($list, $params = array()) 
	{

		if (PHP_SAPI == 'cli') 
		{
			exit('This example should only be run from a Web Browser');
		}

		require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';
		$data = m('common')->getSysset('shop');
		$excel = new PHPExcel();
		$excel->getProperties()->setCreator((empty($data['name']) ? '人人商城' : $data['name']))->setLastModifiedBy((empty($data['name']) ? '人人商城' : $data['name']))->setTitle('Office 2007 XLSX Test Document')->setSubject('Office 2007 XLSX Test Document')->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')->setKeywords('office 2007 openxml php')->setCategory('report file');
		$sheet = $excel->setActiveSheetIndex(0);
		$rownum = 1;

		foreach ($params['columns'] as $key => $column ) 
		{
			$sheet->setCellValue($this->column($key, $rownum), $column['title']);
			if (!(empty($column['width']))) 
			{
				$sheet->getColumnDimension($this->column_str($key))->setWidth($column['width']);
			}
		}

		++$rownum;
		$len = count($params['columns']);
		foreach ($list as $row ) 
		{
			$i = 0;
			while ($i < $len) 
			{
				$value = ((isset($row[$params['columns'][$i]['field']]) ? $row[$params['columns'][$i]['field']] : ''));
				$sheet->setCellValue($this->column($i, $rownum), $value);
				++$i;
			}
			++$rownum;
		}

		$excel->getActiveSheet()->setTitle($params['title']);
		$filename = urlencode($params['title'] . '-' . date('Y-m-d H:i', time()));

		ob_end_clean();
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
		header('Cache-Control: max-age=0');

		/*原本框架的*/
//		$writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
//		$this->SaveViaTempFile($writer);
        /*2019-7-25 修改的*/
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $writer->save('php://output');
		exit();
	}
	public function SaveViaTempFile($objWriter) 
	{
		$filePath = '' . rand(0, getrandmax()) . rand(0, getrandmax()) . '.tmp';
		$objWriter->save($filePath);
		readfile($filePath);
		unlink($filePath);
	}
	public function temp($title, $columns = array()) 
	{
		if (PHP_SAPI == 'cli') 
		{
			exit('This example should only be run from a Web Browser');
		}
		require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';
		$excel = new PHPExcel();
		$excel->getProperties()->setCreator('人人商城')->setLastModifiedBy('人人商城')->setTitle('Office 2007 XLSX Test Document')->setSubject('Office 2007 XLSX Test Document')->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')->setKeywords('office 2007 openxml php')->setCategory('report file');
		$sheet = $excel->setActiveSheetIndex(0);
		$rownum = 1;
		foreach ($columns as $key => $column ) 
		{
			$sheet->setCellValue($this->column($key, $rownum), $column['title']);
			if (!(empty($column['width']))) 
			{
				$sheet->getColumnDimension($this->column_str($key))->setWidth($column['width']);
			}
		}
		++$rownum;
		$len = count($columns);
		$k = 1;
		while ($k <= 5000) 
		{
			$i = 0;
			while ($i < $len) 
			{
				$sheet->setCellValue($this->column($i, $rownum), '');
				++$i;
			}
			++$rownum;
			++$k;
		}
		$excel->getActiveSheet()->setTitle($title);
		$filename = urlencode($title);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
		header('Cache-Control: max-age=0');
		$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
		$writer->save('php://output');
		exit();
	}
	public function import($excefile) 
	{
		global $_W;
		require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';
		require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel/IOFactory.php';
		require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel/Reader/Excel5.php';
		$path = IA_ROOT . '/addons/ewei_shopv2/data/tmp/';
		if (!(is_dir($path))) 
		{
			load()->func('file');
			mkdirs($path, '0777');
		}
		$filename = $_FILES[$excefile]['name'];
		$tmpname = $_FILES[$excefile]['tmp_name'];
		if (empty($tmpname)) 
		{
			message('请选择要上传的Excel文件!', '', 'error');
		}
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (($ext != 'xlsx') && ($ext != 'xls')) 
		{
			message('请上传 xls 或 xlsx 格式的Excel文件!', '', 'error');
		}
		$file = time() . $_W['uniacid'] . '.' . $ext;
		$uploadfile = $path . $file;
		$result = move_uploaded_file($tmpname, $uploadfile);
		if (!($result)) 
		{
			message('上传Excel 文件失败, 请重新上传!', '', 'error');
		}
		$reader = PHPExcel_IOFactory::createReader(($ext == 'xls' ? 'Excel5' : 'Excel2007'));
		$excel = $reader->load($uploadfile);
		$sheet = $excel->getActiveSheet();
		$highestRow = $sheet->getHighestRow();
		$highestColumn = $sheet->getHighestColumn();
		$highestColumnCount = PHPExcel_Cell::columnIndexFromString($highestColumn);
		$values = array();
		$row = 1;
		while ($row <= $highestRow) 
		{
			$rowValue = array();
			$col = 0;
			while ($col < $highestColumnCount) 
			{
				$rowValue[] = (string) $sheet->getCellByColumnAndRow($col, $row)->getValue();
				++$col;
			}
			$values[] = $rowValue;
			++$row;
		}
		return $values;
	}

	/*
	 * 订单数据导出excel 2019-7-12
	 **/
    public function export2($list)
    {
        require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel.php';
        require_once IA_ROOT . '/framework/library/phpexcel/PHPExcel/IOFactory.php';
        $PHPExcel = new PHPExcel();
        //设置excel属性基本信息
        $PHPExcel->getProperties()->setCreator("集和堂药品批发商城")
            ->setLastModifiedBy("集和堂药品批发商城")
            ->setTitle("集和堂药品批发商城")
            ->setSubject("订单列表")
            ->setDescription("")
            ->setKeywords("订单列表")
            ->setCategory("");
        $PHPExcel->setActiveSheetIndex(0);
        $PHPExcel->getActiveSheet()->setTitle("订单列表");
        //填入表头主标题
        $PHPExcel->getActiveSheet()->setCellValue('A1', '集和堂药品批发商城订单列表');
        //合并表头单元格
        $PHPExcel->getActiveSheet()->mergeCells('A1:X1');
        //设置表头行高
        $PHPExcel->getActiveSheet()->getRowDimension(1)->setRowHeight(40);
        $PHPExcel->getActiveSheet()->getRowDimension(3)->setRowHeight(30);

        //设置表头字体
        $PHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setName('黑体');
        $PHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(20);
        $PHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        $PHPExcel->getActiveSheet()->getStyle('A3:X3')->getFont()->setBold(true);

        //设置单元格边框
        $styleArray = array(
            'borders' => array(
                'allborders' => array(
                    //'style' => PHPExcel_Style_Border::BORDER_THICK,//边框是粗的
                    'style' => PHPExcel_Style_Border::BORDER_THIN,//细边框
                    //'color' => array('argb' => 'FFFF0000'),
                ),
            ),
        );

        //表格宽度
        $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(18);
        $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(35);
        $PHPExcel->getActiveSheet()->getColumnDimension('S')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('T')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('U')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('V')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('W')->setWidth(15);
        $PHPExcel->getActiveSheet()->getColumnDimension('X')->setWidth(15);

        //表格标题
        $PHPExcel->getActiveSheet()->setCellValue('A3', '订单编号');
        $PHPExcel->getActiveSheet()->setCellValue('B3', '客户名称');
        $PHPExcel->getActiveSheet()->setCellValue('C3', '收货人姓名');
        $PHPExcel->getActiveSheet()->setCellValue('D3', '收货人电话');
        $PHPExcel->getActiveSheet()->setCellValue('E3', '收货地址');
        $PHPExcel->getActiveSheet()->setCellValue('F3', '下单时间');
        $PHPExcel->getActiveSheet()->setCellValue('G3', '付款时间');
        $PHPExcel->getActiveSheet()->setCellValue('H3', '发货时间');
        $PHPExcel->getActiveSheet()->setCellValue('I3', '完成时间');
        $PHPExcel->getActiveSheet()->setCellValue('J3', '状态');
        $PHPExcel->getActiveSheet()->setCellValue('K3', '支付方式');
        $PHPExcel->getActiveSheet()->setCellValue('L3', '应收款');
        $PHPExcel->getActiveSheet()->setCellValue('M3', '配送方式');
        $PHPExcel->getActiveSheet()->setCellValue('N3', '维权状态');
        $PHPExcel->getActiveSheet()->setCellValue('O3', '订单备注');
        $PHPExcel->getActiveSheet()->setCellValue('P3', '卖家订单备注');
        $PHPExcel->getActiveSheet()->setCellValue('Q3', '退款方式');
        $PHPExcel->getActiveSheet()->setCellValue('R3', '商品名称');
        $PHPExcel->getActiveSheet()->setCellValue('S3', '商品编码');
        $PHPExcel->getActiveSheet()->setCellValue('T3', '商品数量');
        $PHPExcel->getActiveSheet()->setCellValue('U3', '发货数');
        $PHPExcel->getActiveSheet()->setCellValue('V3', '发货单号');
        $PHPExcel->getActiveSheet()->setCellValue('W3', '退货数');
        $PHPExcel->getActiveSheet()->setCellValue('X3', '商品单价');

        $hang = 4;
        foreach ($list as $row) {

            $shuliang   = 0;
            $chanpin    = $hang;
            foreach ($row['goods'] as $kg=>$vg) {
                $shuliang = $shuliang + 1;
                //输出订单的商品，由于可能一个人购买多个商品，所以在这先输出了
                $PHPExcel->getActiveSheet()->setCellValue('R' . $chanpin, $vg['title']);
                $PHPExcel->getActiveSheet()->setCellValue('S' . $chanpin, $vg['goodssn']);
                $PHPExcel->getActiveSheet()->setCellValue('T' . $chanpin, $vg['total']);
                $PHPExcel->getActiveSheet()->setCellValue('U' . $chanpin, $vg['export_num']);
                $PHPExcel->getActiveSheet()->setCellValue('V' . $chanpin, $vg['erp_trade_no']);
                $PHPExcel->getActiveSheet()->setCellValue('W' . $chanpin, $vg['refundnum']);
                $PHPExcel->getActiveSheet()->setCellValue('X' . $chanpin, $vg['dan_price']);
                $chanpin      = $chanpin + 1;
            }

            for ($kk = $hang; $kk < ($hang + $shuliang); $kk++) {
                //合并单元格
                $PHPExcel->getActiveSheet()->mergeCells('A' . $hang . ':A' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('B' . $hang . ':B' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('C' . $hang . ':C' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('D' . $hang . ':D' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('E' . $hang . ':E' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('F' . $hang . ':F' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('G' . $hang . ':G' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('H' . $hang . ':H' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('I' . $hang . ':I' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('J' . $hang . ':J' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('K' . $hang . ':K' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('L' . $hang . ':L' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('M' . $hang . ':M' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('N' . $hang . ':N' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('O' . $hang . ':O' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('P' . $hang . ':P' . $kk);
                $PHPExcel->getActiveSheet()->mergeCells('Q' . $hang . ':Q' . $kk);
            }
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['ordersn']." ");//加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['company']);
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['addressdata']['realname']);
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['addressdata']['mobile']);
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['addressdata']['address']);
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['createtime']);
            $PHPExcel->getActiveSheet()->setCellValue('G' . ($hang), $row['paytime']);
            $PHPExcel->getActiveSheet()->setCellValue('H' . ($hang), $row['sendtime']);
            $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['finishtime']);
            $PHPExcel->getActiveSheet()->setCellValue('J' . ($hang), $row['status']);
            $PHPExcel->getActiveSheet()->setCellValue('K' . ($hang), $row['paytype']);
            $PHPExcel->getActiveSheet()->setCellValue('L' . ($hang), $row['price']);
            $PHPExcel->getActiveSheet()->setCellValue('M' . ($hang), $row['dispatchname']);
            $PHPExcel->getActiveSheet()->setCellValue('N' . ($hang), $row['refundstatus']);
            $PHPExcel->getActiveSheet()->setCellValue('O' . ($hang), $row['remark']);
            $PHPExcel->getActiveSheet()->setCellValue('P' . ($hang), $row['remarksaler']);
            $PHPExcel->getActiveSheet()->setCellValue('Q' . ($hang), $row['refundes']);

            $hang = $hang + $shuliang;
        }
        //设置单元格边框
        $PHPExcel->getActiveSheet()->getStyle('A1:X'.$hang)->applyFromArray($styleArray);
        //设置自动换行
        $PHPExcel->getActiveSheet()->getStyle('A3:X'.$hang)->getAlignment()->setWrapText(true);
        //设置字体大小
        $PHPExcel->getActiveSheet()->getStyle('A3:X'.$hang)->getFont()->setSize(12);
        //垂直居中
        $PHPExcel->getActiveSheet()->getStyle('A1:X'.$hang)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        //水平居中
        $PHPExcel->getActiveSheet()->getStyle('A1:X'.$hang)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $filename = urlencode('订单数据-'.date('Y-m-d'));
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
        header('Cache-Control: max-age=0');
        $writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel5');
        $writer->save('php://output');
        exit();

    }



}
?>