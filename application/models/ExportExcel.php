<?php
/**
 * @description 导出Excel格式
 * @author by Yaoyuan.
 * @version: 2016-10-17
 * @Time: 2016-10-17 9:37
 */
class ExportExcelModel extends BaseModel {
    /**
     * @param array $info
     * @param $dataList
     * @param $title
     */
    public static function excelExportCsv(array $info,$dataList,$title,$statisticsString) {
        set_time_limit(0);
        $objPHPExcel = new PHPExcel ();
        $i = 2;
        $rangeArray = range('A','Z');
        
        $titleVal = array_values($title);
        
        if ($statisticsString) {
            $objPHPExcel->setActiveSheetIndex(0)->mergeCells( 'A1:H1' );
            $objPHPExcel->setActiveSheetIndex(0)->getDefaultRowDimension()->setRowHeight(68);
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $statisticsString);
            $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1')->getAlignment()->setWrapText(true);
        }
        
        foreach ($titleVal as $ks =>$vs) {
            $objPHPExcel->getActiveSheet(0)->getColumnDimension()->setWidth(30);
            if (2  == $info['mark']) {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($rangeArray[$ks].'2', $vs);
            } else {
                $objPHPExcel->setActiveSheetIndex(0)->setCellValue($rangeArray[$ks].'1', $vs);
            }
            
        }
        foreach ( $dataList as $key => $value ) {
            $valKey = array_values($value);
            
            $j = 0;
            foreach ($valKey as $k=>$v) {
                
                $v = Fn::emojitostr($v);
                $objPHPExcel->getActiveSheet()->getColumnDimension($rangeArray[$k])->setWidth(15);
                if (2  == $info['mark']) {
                    $objPHPExcel->getActiveSheet()->setCellValue ( $rangeArray[$k].($key+3),$v );
                } else {
                    $objPHPExcel->getActiveSheet()->setCellValue ( $rangeArray[$k].($key+2),$v );
                }
                
                $j++;
            }
            $i++;
        }
//        ob_end_clean();
        $csv = PHPExcel_IOFactory::createWriter($objPHPExcel,'Excel5');
        
        $name = iconv("utf-8", "gb2312", $info['title']);
        $date = date('Ymd');
        $excelSavePath = dirname(dirname(dirname(__FILE__))).'/excel/';
        Fn::writeLog("导出存放路径".$excelSavePath);
        if (!is_dir($excelSavePath)) {
            mkdir($excelSavePath,0777);
            chmod($excelSavePath,0777);
        }
        
        $filename = $excelSavePath.'MingDan_'.$date.'.xls';
       
        $result = $csv->save($filename);
        Fn::sendMail($info['email'],$info['title'],'尊敬的用户，您好！
      活动相关的表单已为您导出，请查看附件。',$filename);
        
        if ($result) {
            return true;
        } else {
            return false;
        }
    }
}