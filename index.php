<?php
/**
 * Created by PhpStorm.
 * User: denny
 * Date: 2017/8/18
 * Time: 15:18
 */
echo '<pre>';
error_reporting(E_ALL);
ini_set('display_errors','On');
register_shutdown_function(function(){echo '<pre>';print_r(error_get_last());});
try{
    require 'sdk/voypay.php';

    $config = array(
        'mer_no' => '100001',
        'accesskey'=>'9690178FC665A57521016F70A6D2F842',
        'mode'=>'test',
        'debug'=>false,
    );

    $param = array(
        'voypay_trade_no'=>'1708021117083837896'
    );
    $m = new \voypay\refund($config);

    $trade = $m->get($param);
    print_r($trade);
}
catch (Exception $e){
    print_r($e);
}
