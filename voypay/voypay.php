<?php
/**
 * Created by PhpStorm.
 * User: voypay
 * Date: 2017/6/23
 * Time: 18:03
 */
class voypay extends PaymentModule{
    protected $_errors = array();
    public function __construct(){
        $this->name = 'voypay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.1';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');
        $this->author = 'Voypay Ltd';
        $this->display = 'view';
        $this->module_key = md5('voypay');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = 'voypay';
        $this->description = 'Accepts payments by Voypay';
        $this->confirmUninstall =  "Are you sure you want to delete your details ? voypay";
    }
    public function install(){
        // Install default
        if (!parent::install()) {
            return false;
        }
        if (!Configuration::updateValue('VOYPAY_API_MERID', '')
            || !Configuration::updateValue('VOYPAY_API_KEY', '')
            || !Configuration::updateValue('VOYPAY_SANDBOX', 1)
        ) {
            $this->_errors[] = 'There was an Error installing the module on update configuration.';
            return false;
        }

        if (!$this->installOrderState()) {
            $this->_errors[] = 'There was an Error installing the module on installOrderState.';
            return false;
        }

        if( !$this->registerHook('payment')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('paymentReturn')
        ){
            $this->_errors[] = 'There was an Error installing the module on registerHook.';
            return false;
        }
        if(!$this->createVoypayPaymentDb()){
            $this->_errors[] = 'There was an Error installing the module on createDbtable.';
            return false;
        };
        return true;
    }

    public function installOrderState()
    {
        if (!Configuration::get('VOYPAY_OS_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('VOYPAY_OS_WAITING')))
        ) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'En attente de paiement Voypay';
                } else {
                    $order_state->name[$language['id_lang']] = 'Awaiting for Voypay payment';
                }
            }
            $order_state->send_email = false;
            $order_state->color = '#4169E1';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_.'voypay/logo.png';
                $destination = _PS_ROOT_DIR_.'/img/os/'.(int) $order_state->id.'.gif';
                copy($source, $destination);
            }

            Configuration::updateValue('VOYPAY_OS_WAITING', (int) $order_state->id);
        }
        return true;
    }
    /* Create Database Voypay Payment */
    protected function createVoypayPaymentDb()
    {
        $db = Db::getInstance();
        $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'voypay_order` (
			`id_order` int(10) unsigned NOT NULL auto_increment,
			`status` varchar(255) NOT NULL,
			`voypay_trade_no` varchar(20),
			`add_time` int(11) DEFAULT NULL,
            `update_time` int(11) DEFAULT NULL,
			PRIMARY KEY (`id_order`)
			) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $db->Execute($query);

        return true;
    }
    public function uninstall(){
        $config = array(
            'VOYPAY_API_MERID',
            'VOYPAY_API_KEY',
            'VOYPAY_SANDBOX',
        );
        foreach ($config as $var) {
            Configuration::deleteByName($var);
        }
        if (!parent::uninstall()) {
            return false;
        }
        return true;
    }

    private function postProcess()
    {
        if (Tools::isSubmit('voypayConfigSubmit')) {
            $post_errors = array();

            if (!Tools::getValue('mer_no')) {
                $post_errors[] = $this->l('Voypay API mer_no cannot be empty');
            }

            if (!Tools::getValue('mer_key')) {
                $post_errors[] = $this->l('Voypay API mer_key cannot be empty');
            }

            if (empty($post_errors)) {
                Configuration::updateValue('VOYPAY_SANDBOX', (int)Tools::getValue('sandbox'));
                Configuration::updateValue('VOYPAY_API_MERID', trim(Tools::getValue('mer_no')));
                Configuration::updateValue('VOYPAY_API_KEY', trim(Tools::getValue('mer_key')));

                $this->context->smarty->assign('voypay_save_success', true);
                Logger::addLog('voypay configuration updated', 1, null);
            } else {
                $this->context->smarty->assign('voypay_save_fail', true);
                $this->context->smarty->assign('voypay_errors', $post_errors);
            }
        }
    }

    public function getContent()
    {
        $this->postProcess();

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path
        ));

        $html = $this->display(__FILE__, 'views/templates/admin/config.tpl');
        return $html.$this->displayForm();
    }

    private function displayForm()
    {

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $lang->id;
        $helper->identifier = $this->identifier;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'voypayConfigSubmit';
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.
                    '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Voypay Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => (_PS_VERSION_ < '1.6' ? 'radio':'switch'),
                        'label' => $this->l('Sandbox mode'),
                        'name' => 'sandbox',
                        'is_bool' => true,
                        'class' => 't',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'mer_no',
                        'label' => $this->l('API Merchant No'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'mer_key',
                        'label' => $this->l('API Merchant Key'),
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right button',
                )
            )
        );

        $fields_value = array(
            'sandbox' => Configuration::get('VOYPAY_SANDBOX'),
            'mer_no' => Configuration::get('VOYPAY_API_MERID'),
            'mer_key' => Configuration::get('VOYPAY_API_KEY'),
        ) ;

        $helper->tpl_vars = array(
            'fields_value' => $fields_value,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));

    }

    public function hookOrderConfirmation($params){

        $this->context->smarty->assign('stripe_order_reference', pSQL($params['order']->reference));
        if ($params['order']->module == $this->name) {
            return $this->display(__FILE__, 'views/templates/front/order-confirmation.tpl');
        }
    }

    public function hookPayment($params)
    {
        $this->smarty->assign(array(
            'this_path' =>  $this->_path,
            ));
        return  $this->display(__FILE__, 'payment.tpl');
    }

    public function hookDisplayPaymentEU($params)
    {
        return ;
    }

    public function hookPaymentReturn($params){
        global $smarty;
        $state = $params['objOrder']->getCurrentState();
        if ($state == _PS_OS_PAYMENT_){
            $smarty->assign(array(
                'status' => 'ok',
                'id_order' => $params['objOrder']->id
            ));
        }
        else{
            $smarty->assign('status', 'failed');
        }

        return $this->display(__FILE__, 'confirmation.tpl');

    }

    protected function generateForm()
    {

        $context = $this->context;

//        $amount = $context->cart->getOrderTotal();
//        $currency = $context->currency->iso_code;
//        $address_delivery = new Address($context->cart->id_address_delivery);

        $domain = $context->link->getBaseLink($context->shop->id, true);

        $this->context->smarty->assign(
            array(

                'formAction' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
                'baseDir' => $domain,
                'module_dir' => $this->_path,
            )
        );
        return $this->context->smarty->fetch('module:voypay/views/templates/hook/payment.tpl');
    }

    public function getVoyConfig()
    {
        $config = array(
            'mer_no' => Configuration::get('VOYPAY_API_MERID'),
            'accesskey'=>Configuration::get('VOYPAY_API_KEY'),
            'mode'=>  (Configuration::get('VOYPAY_SANDBOX')) ? 'test' : 'live',
            'debug'=>false
        );

        return $config;
    }

    public function saveStatus($id_order, $status, $trade_no = null)
    {
        $result = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'voypay_order` where `id_order` = ' . $id_order);
        $now = time();
        if ($result){
            Db::getInstance()->Execute('
				UPDATE `' . _DB_PREFIX_ . 'voypay_order`
				SET `status` = \'' . $status . '\', `voypay_trade_no` = \'' . $trade_no . '\', `update_time` = \''. $now.'\' WHERE `id_order` = ' . $id_order);
        }
        else{
            Db::getInstance()->Execute('
				INSERT INTO `' . _DB_PREFIX_ . 'voypay_order` (`id_order`, `status`,`add_time`)
				VALUES (' . (int)($id_order) . ', \'' . $status . '\', \''.$now.'\')');
        }

    }
}