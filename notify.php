<?php
ob_start();

$rootpath = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
//$rootpath = dirname(dirname(dirname(__FILE__)));

include($rootpath.'/config/config.inc.php');

include(dirname(__FILE__).'/voypay.php');

$voypay = new voypay();
$config = $voypay->getVoyConfig();

$signature = $_SERVER['HTTP_SIGNATURE'];
$rawcontont = file_get_contents("php://input");

$hashsign = hash('sha256',$config['accesskey'].$rawcontont);

$backparam = json_decode($rawcontont,true,512,JSON_BIGINT_AS_STRING);


$out_trade_no	= $backparam['merchant_trade_no'];	//获取订单号
$trade_no	= $backparam['voypay_trade_no'];		//获取支付宝交易号
$total_fee	= $backparam['amount'];			//获取总价格

$order = new Order($out_trade_no);

if(strtoupper($signature) == strtoupper($hashsign) ) {//验证成功
    if($backparam['status'] == '02')
    {
        $voypay->saveStatus($out_trade_no, $backparam['status'], $trade_no);
        $history = new OrderHistory();
        $history->id_order = (int)($out_trade_no);
        $history->changeIdOrderState(_PS_OS_PAYMENT_, intval($out_trade_no));
        $history->add();
    }
    elseif($backparam['status'] == '03')
    {
        $voypay->saveStatus($out_trade_no, $backparam['status'], $trade_no);
        $history = new OrderHistory();
        $history->id_order = $out_trade_no;
        $history->changeIdOrderState(_PS_OS_ERROR_, intval($out_trade_no));
        $history->add();
    }
    ob_end_clean();
    exit('success');
}
else {
    ob_end_clean();
    exit('fail');
}
