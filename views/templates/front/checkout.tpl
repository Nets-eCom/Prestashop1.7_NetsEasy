{*
* Prestaworks AB
*
* NOTICE OF LICENSE
*
* This source file is subject to the End User License Agreement(EULA)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://license.prestaworks.se/license.html
*
* @author Prestaworks AB <info@prestaworks.se>
* @copyright Copyright Prestaworks AB (https://www.prestaworks.se/)
* @license http://license.prestaworks.se/license.html
*}

{extends $layout}

{block name='content'}

{if $easy_live_mode == 1}
    <script type='text/javascript' src='https://checkout.dibspayment.eu/v1/checkout.js?v=1'></script>
{else}
    <script type='text/javascript' src='https://test.checkout.dibspayment.eu/v1/checkout.js?v=1'></script>
{/if}

<script>
    paymentId               = "{$paymentId nofilter}";
    checkoutKey             = "{$checkoutKey}";
    checkoutLanguage        = "{$checkoutLanguage}";
    confirmation_url        = "{$confirmation_url nofilter}";
    pwdc_checkout_url       = "{$pwdc_checkout_url nofilter}";
</script>

<div class="row" id="waiting-for-redirect" style="text-align: center; display: none; margin-bottom: 12px;">
    <div class="col-lg-12">
        <h4 class="h4">
            {l s='You will be redirected to the confirmation page shortly' mod='easycheckout'}
        </h4>
        <div class="hx-co-loader" style="padding-top: 25px; padding-bottom: 35px; margin-bottom: 15px;">
            <div class="loader loader-sm loader__checkout-start"></div>
        </div>
    </div>
</div>

<div class="pwc">
    <div class="row">
        <div class="col-xs-12 col-md-12">
            <div class="row {if $freeze_waiting_for_3d_response}easy_disable{/if}" id="dynamic_cart_row">
                <div class="cart-grid-body col-xs-12 col-lg-8">
                    <div class="card cart-container">
                        <div class="card-block">
                            <h1 class="h1">{l s='Cart summary' mod='easycheckout'}</h1>
                        </div>
                        <hr class="separator">
                        {include file='checkout/_partials/cart-detailed.tpl' cart=$cart}
                    </div>
                    <!-- shipping informations -->
                    {block name='hook_shopping_cart_footer'}
                      {hook h='displayShoppingCartFooter'}
                    {/block}
                    
                    {if $pwdc_show_shoppinglink === 1 || $pwdc_show_paymentlink === 1}
                        {block name='continue_shopping'}
                            <div class="row">
                                <div class="col-lg-12">
                                    {if $pwdc_show_shoppinglink === 1}
                                        <a href="{$urls.pages.index}" id="pwdc_other_paymentmethods" class="label clearfix easy-link easy-link-left btn btn-default" title="{l s='Continue shopping' mod='easycheckout'}">
                                            <i class="material-icons">keyboard_arrow_left</i>{l s='Continue shopping' mod='easycheckout'}
                                        </a>
                                    {/if}
                                    {if $pwdc_show_paymentlink === 1}
                                        <a href="{$link->getPageLink('order', true)}?step=1" id="pwdc_other_paymentmethods" class="label clearfix easy-link easy-link-right btn btn-default" title="{l s='Other payment options' mod='easycheckout'}" >
                                            {l s='Other payment options' mod='easycheckout'}<i class="material-icons">keyboard_arrow_right</i>
                                        </a>
                                    {/if}
                                </div>
                            </div>
                        {/block}
                    {/if}
                </div>
                <div class="cart-grid-right col-xs-12 col-lg-4">
                    {block name='cart_summary'}
                        <div class="card cart-summary">
                            {block name='hook_shopping_cart'}
                                {hook h='displayShoppingCart'}
                            {/block}
                            {block name='cart_totals'}
                                {include file='checkout/_partials/cart-detailed-totals.tpl' cart=$cart}
                            {/block}
                        </div>
                    {/block}
                    {block name='hook_reassurance'}
                        {hook h='displayReassurance'}
                    {/block}
                </div>
            </div>
        </div>
    </div>

    <div id="no_available_easy_products" style="display:{if isset($available_product_easy) AND $available_product_easy=='no'}block{else}none{/if}">
        <ul class="alert alert-danger">
            <li>{l s='Could not proceede to checkout, one or more products in your cart are not available' mod='easycheckout'}</li>
        </ul>
    </div>

    <div id="no_available_easy_carriers" style="display:{if isset($available_carrier_easy) AND $available_carrier_easy=='no'}block{else}none{/if}">
        <ul class="alert alert-danger">
            <li>{l s='Could not proceede to checkout, no carriers available' mod='easycheckout'}</li>
            <li><a href="{$easyrestartlink}">{l s='Restart the order process' mod='easycheckout'}</a></li>
        </ul>
    </div>

    <div class="row" id="check_cart_easy">
        <div class="col-xs-12 col-md-12 col-lg-5" id="dynamic_changes">
            <div class="row">
                {if isset($delivery_option_list)}
                <div class="easy-carriers-grid cart-grid-body col-xs-12 col-md-6 col-lg-12" style="margin: 0;">                
                    <div class="card"> 
                        <div id="pwdc_deliveryoptions" {if $freeze_waiting_for_3d_response}class="easy_disable"{/if}>
                            {include file="module:easycheckout/views/templates/front/deliveryoptions.tpl"}
                        </div>
                    </div>
                </div>
                {/if}
                <div id="pwdc_messages" class="easy-messages-grid cart-grid-body col-xs-12 col-md-6 col-lg-12 {if $freeze_waiting_for_3d_response}easy_disable{/if}" style="margin: 0;">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-block easy-trigger" id="message">
                                    <h1 class="h1 easy-trigger {if !$message.message}easy-trigger--inactive{/if}" >
                                        {l s='Message' mod='easycheckout'}<span class="material-icons"></span>
                                    </h1>
                                </div>
                                <div class="easy-target" {if !$message.message}style="display: none;"{/if} id="message_container">
                                    <hr class="separator">
                                    <p class="card-block" id="messagearea" style="margin-bottom: 0px;">
                                        <textarea id="order_message" class="easy-input easy-input--area easy-input--full" placeholder="{l s='Add additional information to your order (optional)' mod='easycheckout'}">{$message.message|escape:'htmlall':'UTF-8'}</textarea>
                                        <button class="btn btn-primary" id="savemessagebutton" value="save">{l s='Save' mod='easycheckout'}</button>
                                    </p>
                                </div>
                            </div>
                        </div>
                        {if $giftAllowed == 1}
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-block easy-trigger" id="giftwrapping">
                                    <h1 class="h1 easy-trigger {if $gift_message == '' && (!isset($gift) || $gift==0)}easy-trigger--inactive{/if}">
                                        {l s='Giftwrapping' mod='easycheckout'}<span class="material-icons"></span>
                                    </h1>
                                </div>
                                <div class="easy-target" {if $gift_message == '' && (!isset($gift) || $gift==0)}style="display: none;"{/if} id="giftwrapping_container">
                                    <hr class="separator">
                                    <p class="card-block" id="giftmessagearea_long" style="margin-bottom: 0px;">
                                        <textarea id="gift_message" class="easy-input easy-input--area easy-input--full" placeholder="{l s='Gift message (optional)' mod='easycheckout'}">{$gift_message|escape:'htmlall':'UTF-8'}</textarea>
                                        <span class="easy-check-group">
                                            <input type="checkbox" style="margin: 4px;" onchange="changeGift()" class="giftwrapping_radio" id="gift" value="1"{if isset($gift) AND $gift==1} checked="checked"{/if} />
                                            <span id="giftwrappingextracost">{l s='Additional cost:' mod='easycheckout'}&nbsp;<strong>{$gift_wrapping_price}</strong></span>
                                        </span>
                                        <button class="btn btn-primary" id="savegiftbutton" value="save">{l s='Save' mod='easycheckout'}</button>
                                    </p>
                                </div>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
        <div class="easy-iframe-grid cart-grid-body col-xs-12 col-md-12 col-lg-7">
            <div class="card">
                <div class="card-block">
                    <h1 class="h1">
                         {l s='Complete your purchase' mod='easycheckout'}
                     </h1>
                </div>
                <hr class="separator">
                <div class="card-block">
                    <div class="pwdc_checkout_iframe">
                        <div id="easy-complete-checkout" style="display:{if (isset($available_carrier_easy) AND $available_carrier_easy=='no') OR (isset($available_product_easy) AND $available_product_easy=='no')}none{else}block{/if}">
                        </div>
                    </div>
                </div>
           </div>
        </div>
        <div id="pwc-full-loader" class="pwc-full-loader pwc-full-loader--light" style="display: none;">
          <div class="pwc-loader pwc-loader--dark"></div>
        </div>
    </div>
</div>

<script>
var checkoutOptions = {
    checkoutKey : checkoutKey,
    paymentId   : paymentId,
    containerId : "easy-complete-checkout",
    language    : checkoutLanguage
};
    
var checkout = new Dibs.Checkout(checkoutOptions);
var easy_current_country = "{$easy_current_country}";

</script>

{/block}