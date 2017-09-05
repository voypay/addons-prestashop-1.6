{*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{capture name=path}{l s='Pay by your Credit' mod='voypay'}{/capture}
<h2>{l s='CONFIRM YOUR ORDER' mod='dhpay'}</h2>
{assign var='current_step' value='dhpay'}
{include file="$tpl_dir./order-steps.tpl"}
<div style="margin: 15px 30px">
<p>
    <img src="{$this_path}payments.png" alt="{l s='Pay by Voypay' mod='voypay'}" style="float:left; margin: 0px 10px 5px 0px;" />
    {l s='You have chosen to pay by Voypay.' mod='voypay'}
</p>
<p style="margin-top:20px;">
    {l s='Your order has been generated, and the cart has been emptied.' mod='voypay'}
    <br />
    {l s='Here is a short summary of your order:' mod='voypay'}
</p>
<p>
    - {l s='The total amount of your order is' mod='voypay'}
    <span id="amount" class="price">{displayPrice price=$v_amount}</span>
</p>
</div>

<iframe style = "border:none; width:100%; height:500px;" src="{$iframe_url|escape:'htmlall':'UTF-8'}"></iframe>
