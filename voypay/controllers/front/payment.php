<?php
/**
 * Created by PhpStorm.
 * User: denny
 * Date: 2017/8/17
 * Time: 18:20
 */
class VoypayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;

        $res = $this->generateOrder($cart);

        $this->context->smarty->assign(array(
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'this_path' => $this->module->getPathUri(),
            'this_path_bw' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
            'iframe_url'=>$res,
        ));

        $this->setTemplate('payment_execution.tpl');
    }


    public function generateOrder($cart, &$errors)
    {
        global $cookie, $smarty;

        if ($cart->id_customer != $cookie->id_customer) {
            die("Invalid request");
        }


        $currency_order = new Currency(intval($cart->id_currency));

        $assign_values['currency_order'] = $currency_order->iso_code;

        $v_amount = Tools::ps_round(floatval($cart->getOrderTotal(true, 3)), 2);
        $assign_values['v_amount'] = $v_amount;


        $delivery_addr = new Address(intval($cart->id_address_delivery));

        $id_order = Order::getOrderByCartId($cart->id);
        //$id_order = time();

        if ($id_order) {
            $order = new Order($id_order);
        } else {
            $this->module->validateOrder($cart->id, Configuration::get('VOYPAY_OS_WAITING'), $v_amount, $this->module->displayName, $this->module->l("Waiting for payment"));
            $order = new Order($this->module->currentOrder);
            $id_order = $this->module->currentOrder;
        }
        $this->module->saveStatus($id_order, '00');

        $assign_values['id_order'] = $id_order;
        $assign_values['v_amount'] = $v_amount;
        $smarty->assign($assign_values);

        $customer = new Customer(intval($cart->id_customer));

        $billing_addr = new Address(intval($cart->id_address_invoice));

        $order_product = $cart->getProducts();
        $goodsInfo = array();
        foreach ((array)$order_product as $item){
            $product = array(
                'goods_name'=>$item['name'],
                'quantity'=>$item['cart_quantity'],
                'price'=>sprintf("%.2f", $item['price'])
            );
            $goodsInfo[] = $product;
        }

        $billingAddr = array(
            'first_name'=>$billing_addr->firstname,
            'last_name'=>$billing_addr->lastname,
            'address1'=>$billing_addr->address1,
            'address2'=>$billing_addr->address2,
            'zip_code'=>$billing_addr->postcode,
            'city'=>$billing_addr->city,

            'state'=>($billing_addr->id_state) ? State::getNameById($billing_addr->id_state) : $billing_addr->city,
            'country'=>Country::getIsoById($billing_addr->id_country)
        );
        $shippingAddr = array(
            'first_name'=>$delivery_addr->firstname,
            'last_name'=>$delivery_addr->lastname,
            'address1'=>$delivery_addr->address1,
            'address2'=>$delivery_addr->address2,
            'zip_code'=>$delivery_addr->postcode,
            'city'=>$delivery_addr->city,
            'state'=>($delivery_addr->id_state) ? State::getNameById($delivery_addr->id_state) : $delivery_addr->city,
            'country'=>Country::getIsoById($delivery_addr->id_country)
        );

        $parameter = array(
            'display_type'=>'iframe',
            'merchant_trade_no'=>$id_order,
            'currency'=>$currency_order->iso_code,
            'amount'=>sprintf("%.2f", $cart->getOrderTotal(true, Cart::BOTH)),
            'card_holder'=>$billing_addr->firstname . ' '. $billing_addr->lastname ,
            'buyer_email'=> $customer->email,
            'buyer_phone'=> $customer->email,
            'goods_info'=> $goodsInfo,
            'billing_address'=>$billingAddr,
            'shipping_address'=>$shippingAddr,
            'return_url'=>Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'.'return.php',
            //'notify_url'=>Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'.'notify.php',
            'langugage'=>'en',
            'buyer_ip'=>$this->get_client_ip(),
            'user_agent'=>$_SERVER['HTTP_USER_AGENT'],
            'remark'=>'',
        );

        $config = $this->module->getVoyConfig();

        require_once(dirname(__FILE__)."/../../sdk/voypay.php");
        $m = new \voypay\trade($config);
        $sd = $m->forward($parameter);

        return  $sd['forward_url'];

    }
    public function get_client_ip()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $online_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $online_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $online_ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $online_ip = $_SERVER['REMOTE_ADDR'];
        }
        $ips = explode(",", $online_ip);
        return $ips[0];
    }

}