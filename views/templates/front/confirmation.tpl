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
    {if isset($order)}
        {block name='page_content_container' prepend}
            <section id="content_hook_order_confirmation" class="card">
                <div class="card-block">
                    <div class="row">
                        <div class="col-md-12">
                            {block name='order_confirmation_header'}
                                <h3 class="h1 card-title">
                                    <i class="material-icons material-icons-done">check_circle</i>{l s='Your order is confirmed' mod='easycheckout'}
                                </h3>
                            {/block}
                            
                            <p>
                                {l s='A confirmation will be sent to your email' mod='easycheckout'}&nbsp;<strong>{$pwdc_email}
                                {if $order.details.invoice_url}
                                    {l s='You can also download your invoice' mod='easycheckout'}
                                        <a href='{$order.details.invoice_url}'>{l s='here' mod='easycheckout'}</a>
                                {/if}
                            </p>
                            
                            {block name='hook_order_confirmation'}
                                {$HOOK_ORDER_CONFIRMATION nofilter}
                            {/block}
                        </div>
                    </div>
                </div>
            </section>   
        {/block}
    {/if}
    
    {block name='page_content_container'}
        {if isset($order)}
            <section id="content" class="page-content page-order-confirmation card">
                <div class="card-block">
                    <div class="row">
                        {block name='order_confirmation_table'}
                            {include
                                file='checkout/_partials/order-confirmation-table.tpl'
                                products=$order.products
                                subtotals=$order.subtotals
                                totals=$order.totals
                                labels=$order.labels
                                add_product_link=false
                            }
                        {/block}

                        {block name='order_details'}
                            <div id="order-details" class="col-md-4">
                                <h3 class="h3 card-title">{l s='Order details' d='Shop.Theme.Checkout'}:</h3>
                                <ul>
                                    <li>{l s='Order reference: %reference%' d='Shop.Theme.Checkout' sprintf=['%reference%' => $order.details.reference]}</li>
                                    <li>{l s='Payment method: %method%' d='Shop.Theme.Checkout' sprintf=['%method%' => $order.details.payment]}</li>
                                    {if !$order.details.is_virtual}
                                        <li>
                                            {l s='Shipping method: %method%' d='Shop.Theme.Checkout' sprintf=['%method%' => $order.carrier.name]}<br>
                                            <em>{$order.carrier.delay}</em>
                                        </li>
                                    {/if}
                                </ul>
                            </div>
                        {/block}
                    </div>
                </div>
            </section>
        {/if}
        
        {block name='hook_order_confirmation_1'}
            {hook h='displayOrderConfirmation1'}
        {/block}
    {/block}
{/block}