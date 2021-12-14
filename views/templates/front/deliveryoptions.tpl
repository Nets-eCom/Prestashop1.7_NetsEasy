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

<div class="card-block">
    <h1 class="h1">{l s='Delivery options' mod='easycheckout'}</h1>
</div>
<hr class="separator">
<div class="card-block" style="padding-bottom: 10px;">
    {foreach $delivery_option_list as $id_address => $option_list}
    <ul class="easy-sel-list has-tooltips">
        {foreach $option_list as $key => $option}
        <div class="col-lg-12" style="padding: 0px 0px 6px 0px;">
            <li data-id-carrier="{$key|intval}" style="margin: 0px" class="easy-sel-list__item {if isset($delivery_option[$id_address]) && $delivery_option[$id_address] == $key}selected{/if}" onclick="onDeliveryClick('{$id_address}', '{$key}', 'delivery_option_{$id_address|intval}_{$option@index|escape:'html':'UTF-8'}')" id="delivery_option_{$id_address|intval}_{$option@index|escape:'html':'UTF-8'}">
                <label for="delivery_option_{$id_address|intval}_{$option@index|escape:'html':'UTF-8'}" class="easy-sel-list__item__label">
                    <span class="easy-sel-list__item__status">
                        <i class="material-icons"></i>
                    </span>
                   <span class="easy-sel-list__item__logo">
                        {if $option.unique_carrier}
                            {foreach $option.carrier_list as $carrier}
                                {if $carrier.logo}
                                    <img src="{$carrier.logo|escape:'html':'UTF-8'}" alt="{$carrier.instance->name|escape:'html':'UTF-8'}"/>
                                {/if}
                            {/foreach}
                        {/if}
                    </span>
                    <span class="easy-sel-list__item__title">
                        {if $option.unique_carrier}
                            {foreach $option.carrier_list as $carrier}
                                {$carrier.instance->name|escape:'html':'UTF-8'}
                            {/foreach}
                        {/if}
                    </span>
                    <span class="easy-sel-list__item__nbr">
                        {if $option.total_price_with_tax && !$free_shipping}
                            {Tools::displayPrice($option.total_price_with_tax)}
                        {else}
                            {l s='Free!' mod='easycheckout'}
                        {/if}
                    </span>
                    <span class="easy-sel-list__item__info">
                        {if $option.unique_carrier}
                            {foreach $option.carrier_list as $carrier}
                                {if isset($carrier.instance->delay[$language.id])}
                                    {$carrier.instance->delay[$language.id]}&nbsp;	
                                {/if}
                            {/foreach}
                        {/if}
                    </span>
                </label>
                {if !$option.unique_carrier}
                <table class="delivery_option_carrier {if isset($delivery_option[$id_address|intval]) && $delivery_option[$id_address|intval] == $key}selected{/if}">
                {foreach $option.carrier_list as $carrier}
                    <tr>
                        <td class="first_item">
                            <input type="hidden" value="{$carrier.instance->id|intval}" name="id_carrier" />
                            {if $carrier.logo}
                            <img src="{$carrier.logo|escape:'html':'UTF-8'}" alt="{$carrier.instance->name|escape:'html':'UTF-8'}"/>
                            {/if}
                        </td>
                        <td>
                            {$carrier.instance->name|escape:'html':'UTF-8'}
                        </td>
                    </tr>
                {/foreach}
                </table>
                {/if}
            </li>
        </div>
        {/foreach}
    </ul>
    {/foreach}
</div>
{if isset($left_to_get_free_shipping_price) && $left_to_get_free_shipping_price > 0}
    <div class="card-block" style="padding: 0px; margin-bottom: 15px;">
        <hr class="separator">
        <div class="easy-infobox">
            <span class="material-icons">shopping_basket</span>{l s='You will receive free shipping if you shop for an additional amount of' mod='easycheckout'}&nbsp;<strong>{$left_to_get_free_shipping_price}</strong>
        </div>
        <hr class="separator">
    </div>
{/if}
{if isset($left_to_get_free_shipping_weight) && $left_to_get_free_shipping_weight > 0}
    <div class="card-block" style="padding: 0px; margin-bottom: 15px;">
        <hr class="separator">
        <div class="easy-infobox">
            <span class="material-icons">local_shipping</span>{l s='By shopping for' mod='easycheckout'}&nbsp;<strong>{$left_to_get_free_shipping_weight}&nbsp;{l s='kg' mod='easycheckout'}</strong>&nbsp;{l s='more in weight, you will qualify for free shipping.' mod='easycheckout'}
        </div>
        <hr class="separator">
    </div>
{/if}