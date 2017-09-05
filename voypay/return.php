<?php

$rootpath = dirname(dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
//$rootpath = dirname(dirname(dirname(__FILE__)));

include($rootpath.'/config/config.inc.php');

if (!$cookie->isLogged()) {
    Tools::redirect('authentication.php?back=order.php');
}
include(dirname(__FILE__).'/voypay.php');

$voypay = new voypay();

$out_trade_no	= $_GET['merchant_trade_no'];	//获取订单号
$trade_no	= $_GET['voypay_trade_no'];		//获取支付宝交易号
$total_fee	= $_GET['amount'];			//获取总价格

$order = new Order($out_trade_no);

$config = $voypay->getVoyConfig();


$signature = $_GET['signature'];
unset($_GET['signature']);
$paramstr = http_build_query($_GET);
$hashsign =  hash('sha256',$config['accesskey'].$paramstr);

if(strtoupper($signature) == strtoupper($hashsign)) {//验证成功
    if($_GET['status'] == '02')
    {
        $voypay->saveStatus($out_trade_no, $_GET['status'], $trade_no);
        $history = new OrderHistory();
        $history->id_order = (int)($out_trade_no);
        $history->changeIdOrderState(_PS_OS_PAYMENT_, intval($out_trade_no));
        $history->add();
    }
    elseif($_GET['status'] == '03')
    {
        $voypay->saveStatus($out_trade_no, $_GET['status'], $trade_no);
        $history = new OrderHistory();
        $history->id_order = $out_trade_no;
        $history->changeIdOrderState(_PS_OS_ERROR_, intval($out_trade_no));
        $history->add();
    }
}
else {
    echo $voypay->l("signature error");
}
$order = new Order((int)$out_trade_no);
$customer = new Customer(intval($order->id_customer));
$key = (isset($order)?$order->secure_key:pSQL($customer->secure_key));

Tools::redirect('index.php?controller=order-confirmation&id_cart='.$order->id_cart.'&id_module='.$voypay->id.'&id_order='.$voypay->currentOrder.'&key='.$customer->secure_key);