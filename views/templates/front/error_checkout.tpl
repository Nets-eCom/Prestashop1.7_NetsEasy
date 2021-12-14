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

<div class="pwc">
    <div class="row" id="dynamic_cart_row">
        <div class="cart-grid-body col-xs-12">
            <ul class="alert alert-danger">
            {foreach from=$errorMessages item=errorMessage}
                <li>{$errorMessage}</li>
            {/foreach}
            </ul>

            <div class="card cart-container">
                <div class="card-block">
                    <h1 class="h1">{l s='Cart summary' mod='easycheckout'}</h1>
                </div>
                <hr class="separator">
                {include file='checkout/_partials/cart-detailed.tpl' cart=$cart}
            </div>

            <a href="{$easycheckout_linkback}">{l s='Return to the shop' mod='easycheckout'}</a>
        </div>
    </div>
</div>
{/block}
