{if $module == 'netseasy'}

    <link href="{$url}/views/css/order.css" rel="stylesheet" type="text/css"/>

    <script>
        {literal}
            const testvar = '{/literal}{$id_order}{literal}';

            $(document).ready(() => {
                // reposition Nets Easy Block
                $("#orderNetsOriginalPosition").appendTo($('.order-actions'));
            });
        {/literal}
    </script>


    <div id="orderNetsOriginalPosition">
        <div id="orderNetsPanel" class="card">
            <div class="card-header">
                <h3 class="card-header-title">{$moduleName}</h3>
            </div>
            <div class="card-body"> 
                <div class="nets-container">
                    {if $data.debugMode}
                        <div class="row" style="padding-bottom:0px;margin-right: 3px;">
                            <div style="width:100%">
                                <pre>{$data.printResponseItems}</pre>   
                                <pre>{{$data.apiGetRequest}}</pre> 
                            </div>
                        </div>
                    {/if}
                    <div class="nets-block">
                        <table cellspacing="0" cellpadding="0" border="0" width="100%">
                            <thead>
                                <tr class="lining">
                                    <td class="listing bottom" colspan="2">
                                        <span>{l s="nets_payment_status" d="Modules.Netseasy.Admin_content_order"}  : <b>{$data.status.payStatus}</b></span>
                                    </td>
                                    <td class="listing bottom" colspan="3" style="text-align:right">
                                        <span class="pid">{l s="nets_payment_id" d="Modules.Netseasy.Admin_content_order"} : <b>{{$data.paymentId}}</b></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="center">{l s="nets_quantity" d="Modules.Netseasy.Admin_content_order"}</th>
                                    <th>{l s="nets_model" d="Modules.Netseasy.Admin_content_order"}</th>
                                    <th>{l s="nets_product" d="Modules.Netseasy.Admin_content_order"}</th>
                                        {if isset($data.responseItems.failedItems) || isset($data.responseItems.cancelledItems) || isset($data.responseItems.refundedItems)}
                                        <th class="right" colspan="2">{l s="nets_price" d="Modules.Netseasy.Admin_content_order"}</th>
                                        {else} 
                                        <th class="right">{l s="nets_price" d="Modules.Netseasy.Admin_content_order"}</th>
                                        <th class="right" width="150px">{l s="nets_action" d="Modules.Netseasy.Admin_content_order"}</th>
                                        {/if} 	 
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Table body functions -->

                                <!-- Reserved Payment -->
                                {if $data.responseItems.reservedItems != isset($data.responseItems.cancelledItems)}
                                    {foreach key=nm item=listitem from=$data.responseItems.reservedItems}

                                    <form 
                                        name="partialCharge_{{$listitem.reference}}" 
                                        method="post" 
                                        action="{{$adminurl}}/index.php?controller=AdminNetseasyOrder&action=charge&token={{$user_token}}"
                                        >
                                        <tr class="lining" key="{{$listitem.reference}}">
                                            <td class="listing" width="150px">
                                                <div class="qty-box charge">
                                                    <div class="quantity">
                                                        <input type="hidden" name="quantity" class="quant" value="{{$listitem.quantity}}"/>
                                                        <input type="hidden" name="reference" class="reference" value="{{$listitem.reference}}"/>
                                                        <input type="hidden" name="netprice" class="netprice" value="{{$listitem.netprice}}"/>
                                                        <input type="hidden" name="grossprice" class="grossprice" value="{{$listitem.grossprice}}"/>
                                                        <input type="hidden" name="currency" class="currency" value="{{$listitem.currency}}"/>
                                                        <input type="hidden" name="taxrate" class="taxrate" value="{{$listitem.taxRate}}"/>
                                                        <input type="hidden" name="orderid" value="{{$data.oID}}"/>
                                                        <input type="hidden" name="orderid" value="{{$data.oID}}"/>

                                                        <input type="hidden" name="ordertoken" value="{{$order_token}}"/>

                                                        <input type="button" value="-" class="minus"/> 										
                                                        <input 
                                                            type="text" 
                                                            class="single qty value" 
                                                            name="single" 
                                                            value="{{$listitem.quantity}}" 
                                                            step="1" 
                                                            min="1" 
                                                            max="{{$listitem.quantity}}"
                                                            />
                                                        <input type="button" value="+" class="plus"/>

                                                    </div>
                                                </div>
                                            </td>
                                            <td class="listing">{{$listitem.reference}}</td> 
                                            <td class="listing">{{$listitem.name}}</td>
                                            <td class="listing" style="text-align:right;">
                                                <span id="price_{{$listitem.reference}}" class="priceblk">
                                                    {{$listitem.grossprice}} {{$listitem.currency}}
                                                </span>
                                            </td>
                                            <td class="listing" width="150px" align="right">									
                                                <button 
                                                    type="submit" 
                                                    id="item_{{$listitem.reference}}" 
                                                    class="nets-btn capture" 
                                                    name="charge" 
                                                    value="{{$listitem.quantity}}"
                                                    >
                                                    <span>{l s="nets_charge" d="Modules.Netseasy.Admin_content_order"}</span>
                                                </button>
                                            </td> 
                                        </tr>
                                    </form>

                                {/foreach}
                            {/if}

                            <!-- Charged Payment -->
                            {if isset($data.responseItems.chargedItems)}								
                                {foreach from=$data.responseItems.chargedItems item=prodval}
                                    <form 
                                        name="partialRefund_{{$prodval.reference}}" 
                                        method="post" 
                                        action="{{$adminurl}}/index.php?controller=AdminNetseasyOrder&action=refund&token={{$user_token}}"
                                        >
                                        <tr class="lining" key="{{$prodval.reference}}">
                                            <td class="listing" width="150px">
                                                <div class="qty-box refund">
                                                    <div class="quantity">
                                                        <input type="hidden" name="quantity" class="quant" value="{{$prodval.quantity}}"/>
                                                        <input type="hidden" name="reference" class="reference" value="{{$prodval.reference}}"/>
                                                        <input type="hidden" name="name" value="{{$prodval.name}}"/>
                                                        <input type="hidden" name="netprice" class="netprice" value="{{$prodval.netprice}}"/>
                                                        <input type="hidden" name="grossprice" class="grossprice" value="{{$prodval.grossprice}}"/>
                                                        <input type="hidden" name="currency" class="currency" value="{{$prodval.currency}}"/>
                                                        <input type="hidden" name="taxrate" class="taxrate" value="{{$prodval.taxRate}}"/>
                                                        <input type="hidden" name="orderid" value="{{$data.oID}}"/>
                                                        <input type="button" value="-" class="minus"/>
                                                        <input type="hidden" name="ordertoken" value="{{$order_token}}"/>
                                                        <input 
                                                            type="text" 
                                                            class="single qty value" 
                                                            name="single" 
                                                            value="{{$prodval.quantity}}" 
                                                            step="1" 
                                                            min="1" 
                                                            max="{{$prodval.quantity}}"
                                                            />
                                                        <input type="button" value="+" class="plus"/>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="listing">{{$prodval.reference}}</td>
                                            <td class="listing">{{$prodval.name}}</td>
                                            <td class="listing" style="text-align:right;">									 
                                                <span id="price_{{$prodval.reference}}" class="priceblk">
                                                    {{$prodval.grossprice}} {{$prodval.currency}}
                                                </span>
                                            </td>
                                            <td class="listing right" width="150px">
                                                <button 
                                                    type="submit" 
                                                    id="item_{{$prodval.reference}}" 
                                                    class="nets-btn refund" 
                                                    name="refund" 
                                                    value="{{$prodval.quantity}}"
                                                    />
                                                <span>{l s="nets_refund" d="Modules.Netseasy.Admin_content_order"}</span>
                                                </button>
                                            </td>
                                        </tr>
                                    </form>	
                                {/foreach}
                            {/if}

                            <!-- Refunded Payment -->

                            {if isset($data.responseItems.refundedItems) and $data.status.payStatus == "Refunded"} 								 
                                {foreach from=$data.responseItems.refundedItems item=item}
                                    <tr class="listing">
                                        <td class="listing" style="text-align: center;">{{$item.quantity}}</td>
                                        <td class="listing">{{$item.reference}}</td>
                                        <td class="listing">{{$item.name}}</td>
                                        <td class="listing right" colspan="2">{{$item.grossprice}} {{$item.currency}}</td>
                                    </tr>
                                {/foreach}
                            {/if}

                            <!-- Cancelled Payment -->

                            {if isset($data.responseItems.cancelledItems)} 								 
                                {foreach from=$data.responseItems.cancelledItems item=item}
                                    <tr class="listing">
                                        <td class="listing" style="text-align: center;">{{$item.quantity}}</td>
                                        <td class="listing">{{$item.reference}}</td>
                                        <td class="listing">{{$item.name}}</td>
                                        <td class="listing right" colspan="2">{{$item.grossprice}} {{$item.currency}}</td>
                                    </tr>
                                {/foreach}
                            {/if}

                            <!-- Failed Payment -->

                            {if isset($data.responseItems.failedItems)} 								 
                                {foreach from=$data.responseItems.failedItems item=item}
                                    <tr class="listing">
                                        <td class="listing" style="text-align: center;">{{$item.quantity}}</td>
                                        <td class="listing">{{$item.reference}}</td>
                                        <td class="listing">{{$item.name}}</td>
                                        <td class="listing right" colspan="2">{{$item.grossprice}} {{$item.currency}}</td>
                                    </tr>
                                {/foreach}
                            {/if}


                            <!-- Table footer functions / statuses -->


                            {if $data.status.payStatus == "Reserved"}
                                <tr class="lining">
                                    <td class="listing top">
                                        <form 
                                            name="cancelOrder" 
                                            id="cancelorder" 
                                            action="{{$adminurl}}/index.php?controller=AdminNetseasyOrder&action=cancel&token={{$user_token}}" 
                                            method="post"
                                            >
                                            <input type="hidden" name="orderid" value="{{$data.oID}}"/>
                                            <input type="hidden" name="ordertoken" value="{{$order_token}}"/>
                                            <button 
                                                type="submit" 
                                                id="cancel_all" 
                                                class="nets-btn cancel" 
                                                name="cancel"
                                                >
                                                <span>{l s="nets_cancel_payment" d="Modules.Netseasy.Admin_content_order"}</span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="listing top reserve" colspan="3" style="text-align: center;">
                                        <div class="nets-status">{l s="nets_payment_reserved" d="Modules.Netseasy.Admin_content_order"}</div>
                                    </td>
                                    <td class="listing top" align="right" width="150px">
                                        <form 
                                            name="ChargeAll" 
                                            method="post" 
                                            action="{{$adminurl}}/index.php?controller=AdminNetseasyOrder&action=charge&token={{$user_token}}"
                                            >
                                            <input type="hidden" name="orderid" value="{{$data.oID}}"/>
                                            <input type="hidden" name="ordertoken" value="{{$order_token}}"/>
                                            <button 
                                                type="submit" 
                                                id="charge_all" 
                                                class="nets-btn capture-all" 
                                                name="charge"
                                                >
                                                <span>{l s="nets_charge_all" d="Modules.Netseasy.Admin_content_order"}</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            {elseif $data.status.payStatus == "Charged"}
                                {if not $data.responseItems.reservedItems} 
                                    <tr class="lining">
                                        <td class="listing top" colspan="1">&nbsp;</td>
                                        <td class="listing top charge" colspan="3" style="text-align: center;">
                                            <div class="nets-status">{l s="nets_payment_charged" d="Modules.Netseasy.Admin_content_order"}</div>
                                        </td>
                                        <td class="listing top" align="right" width="150px">
                                            <form 
                                                name="refundAll" 
                                                method="post" 
                                                action="{{$adminurl}}/index.php?controller=AdminNetseasyOrder&action=refund&token={{$user_token}}"
                                                >
                                                <input type="hidden" name="orderid" value="{{$data.oID}}"/>
                                                <input type="hidden" name="ordertoken" value="{{$order_token}}"/>
                                                <button 
                                                    type="submit" 
                                                    id="refund_all" 
                                                    class="nets-btn refund-all" 
                                                    name="refund"
                                                    >
                                                    <span>{l s="nets_refund_all" d="Modules.Netseasy.Admin_content_order"}</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                {/if}

                            {elseif $data.status.payStatus == "Refunded"}
                                <tr class="">
                                    <td class="listing top refund" colspan="5" align="center">
                                        <div class="nets-status">{l s="nets_payment_refunded" d="Modules.Netseasy.Admin_content_order"}</div>
                                    </td>
                                </tr>

                            {elseif $data.status.payStatus == "Refund Pending"}

                                {if $data.responseItems.chargedItems}
                                    <tr class="">
                                        <td class="listing top refund" colspan="5" align="center">
                                            <div class="nets-status">{l s="nets_payment_refund_pending" d="Modules.Netseasy.Admin_content_order"}</div>
                                        </td>
                                    </tr>
                                {/if}
                                {foreach from=$data.responseItems.refundedItems item=item}

                                    <tr class="listing">
                                        <td class="listing" style="text-align: center;">{{$item.quantity}}</td>
                                        <td class="listing">{{$item.reference}}</td>
                                        <td class="listing">{{$item.name}}</td>
                                        <td class="listing right" colspan="2">{{$item.grossprice}} {{$item.currency}}</td>
                                    </tr>
                                {/foreach}
                                {if not $data.responseItems.chargedItems}
                                    <tr class="">
                                        <td class="topbg" colspan="5" align="center">
                                            <div class="nets-status">{l s="nets_payment_refund_pending" d="Modules.Netseasy.Admin_content_order"}</div>
                                        </td>
                                    </tr>
                                {/if}

                            {elseif $data.status.payStatus == "Cancelled"}
                                <tr class="">
                                    <td class="listing top cancel" colspan="5" align="center">
                                        <div class="nets-status">{l s="nets_payment_cancelled" d="Modules.Netseasy.Admin_content_order"}</div>
                                    </td>
                                </tr>

                            {elseif $data.status.payStatus == "Failed"}
                                <tr class="">
                                    <td class="listing top fail" colspan="5" align="center">
                                        <div class="nets-status">{l s="nets_payment_failed" d="Modules.Netseasy.Admin_content_order"}</div>
                                    </td>
                                </tr>

                            {elseif $data.status.payStatus == "Partial Charged"}
                                <tr class="topbg">
                                    <td class="" colspan="5" align="center">
                                        <div class="nets-status">{l s="nets_charged_products" d="Modules.Netseasy.Admin_content_order"}</div>
                                    </td>
                                </tr>

                                {foreach from=$data.responseItems.chargedItemsOnly item=prodval}
                                    <tr class="lining" key="{{$prodval.reference}}">
                                        <td class="listing partial-charge center" width="150px">{{$prodval.quantity}}</td>
                                        <td class="listing partial-charge">{{$prodval.reference}}</td>
                                        <td class="listing partial-charge">{{$prodval.name}}</td>
                                        <td class="listing partial-charge right" colspan="2">{{$prodval.grossprice}} {{$prodval.currency}}</td>
                                    </tr>
                                {/foreach}

                            {elseif $data.status.payStatus == "Partial Refunded"}
                                {if $data.responseItems.chargedItems}
                                    <tr class="">
                                        <td class="listing top refund" colspan="5" align="center">
                                            <div class="nets-status">{l s="nets_refunded_products" d="Modules.Netseasy.Admin_content_order"}</div>
                                        </td>
                                    </tr>
                                {/if}
                                {foreach from=$data.responseItems.refundedItems item=prodval}

                                    <tr class="lining" key="{{$prodval.reference}}">
                                        <td class="listing partial-refund center" width="150px">{{$prodval.quantity}}</td>
                                        <td class="listing partial-refund">{{$prodval.reference}}</td>
                                        <td class="listing partial-refund">{{$prodval.name}}</td>
                                        <td class="listing partial-refund right" colspan="2">{{$prodval.grossprice}} {{$prodval.currency}}</td>
                                    </tr>
                                {/foreach}
                            {/if}
                            </tbody>
                        </table>
                    </div>

                </div>

                <script src="{$url}/views/js/order.js"></script>				


                <div id="testcon"></div>
            </div>
        </div>
    </div>

{/if}
