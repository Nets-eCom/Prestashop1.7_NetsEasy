{block name='content'}
    <div class="card thankyou_page">
        <div class="card-block">
        <div class="row">
            <div class="col-md-12 center_xs">
                <div class="headline_block dark_text">
                    <div class="headline">
                        <span class="material-icons pwc_color pwc_success_icon">check_circle</span>
                            <h3 class="pwc_color" style="font-size: 28px">
                                <strong>{l s='Thank you for your purchase!' mod='easycheckout'}</strong>
                            </h3>        
                    </div>
                    <span>{l s='A confirmation will be sent to your email:' mod='easycheckout'} <strong>{$customer_email}</strong></span>
                </div>
				<div class="dibs_brand_assets" style="margin: 8px;">
					<img class="img-fluid img-responsive" src="https://cdn.dibspayment.com/logo/shop/en/horiz/DIBS_shop_Easy_hori_EN_02.png" alt="DIBS - Payments made easy" width="1170"/>
				</div>
            </div>
        </div>
        <div class="row">    
            <div class="col-md-12">
                <div class="pwc-box padded_box dark_text" style="padding-bottom: 0px;">
                    <div class="row">
                        <div class="col-xs-12 col-md-6">
                            <div style="margin-bottom: 25px;">
                                <strong>{l s='Order reference' mod='easycheckout'}</strong><br>
                                {$reference|escape:'html':'UTF-8'}<br>
                                <br>
                                <strong>{l s='Payment method' mod='easycheckout'}</strong><br>
                                {if $payment.type == INVOICE}
                                    {l s='Invoice, 14 days' mod='easycheckout'}
                                {else if $payment.type == ACCOUNT}
                                    {l s='Part payment, Up to 60 days' mod='easycheckout'}
                                {else if $payment.type == CARD}
                                    {l s='Card payment' mod='easycheckout'}{if isset($payment.card)} {$payment.card}{/if}
                                {else}
                                    {l s='Unknown' mod='easycheckout'}
                                {/if}
                            </div>
                        </div>
                        <div class="col-xs-12 col-md-6">
                            <div style="margin-bottom: 25px;">
                                <strong>{l s='Shipping method' mod='easycheckout'}</strong><br>
                                {$delivery_method.name}<br>
                                <br>
                                <strong>{l s='Delivery address' mod='easycheckout'}</strong><br>
                                {$address}<br>
                                {$zip}&nbsp;&nbsp;{$city}<br> 
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">    
            <div class="col-md-12">
                <div class="pwc-box">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-12"> 
                                    <div class="padded_box dark_text">
                                        <div class="row">
                                            <div class="col-xs-12 col-md-6 center_xs">      
                                                <span class="close_cart_hover" id="close_cart"><strong>{l s='Order details' mod='easycheckout'}&nbsp;<span id="close_cart_sign" style="color: #999;" class="material-icons down"></span></strong></span>
                                            </div>
                                            <div class="col-md-2 table_content table_head">
                                                <strong>{l s='Unit price' mod='easycheckout'}</strong>
                                            </div>
                                            <div class="col-md-2 table_content table_head">
                                                <strong>{l s='Quantity' mod='easycheckout'}</strong>
                                            </div>
                                            <div class="col-md-2 table_content table_head">
                                                <strong>{l s='Total' mod='easycheckout'}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row closeable">
                        <div class="col-md-12">
                            <div class="padded_box border_top light_text">
                                {if (isset($order_rows) && count($order_rows) > 0) || isset($delivery_method)}
                                    {if (isset($order_rows) && count($order_rows) > 0)}
                                        {foreach $order_rows as $rows}
											<div class="row">
												<div class="col-md-12">
													<div class="dynamic_row">
														<div class="row">
															<div class="col-xs-8 col-md-6">
																<strong>{$rows.name}</strong>
																{if isset($rows.description) && $rows.description != ''}
																	<br>{$rows.description}
																{/if}
															</div>
															<div class="col-md-2 table_content table_head">
																{$rows.unitPrice}
															</div>
															<div class="col-md-2 table_content table_head">
																{$rows.quantity}&nbsp;{$rows.unit}
															</div>
															<div class="col-xs-4 table_content col-md-2">
																<strong>{$rows.grossTotalAmount}</strong>
															</div>
														</div>
													</div>
												</div>
											</div>
                                        {/foreach}
                                        {if isset($delivery_method)}
                                         <div class="row">
                                            <div class="col-md-12">
                                                <div class="dynamic_row">
                                                    <div class="row">
                                                        <div class="col-xs-8 col-md-6">
                                                            <strong>{l s='Shipping method' mod='easycheckout'}</strong> 
                                                            <br>{$delivery_method.name}
                                                        </div>
                                                        <div class="col-md-2 table_content table_head">
                                                        </div>
                                                        <div class="col-md-2 table_content table_head">
                                                        </div>
                                                        <div class="col-xs-4 table_content col-md-2">
                                                            <strong>{$delivery_method.price}</strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                            
                                        {/if}
                                    {/if}
                                {else}
                                    <div class="dynamic_row">
                                        {l s='No rows' mod='easycheckout'} 
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                    <div class="row closeable">
                        <div class="col-md-12">
                        
                            <div class="padded_box border_top dark_text">
                                <div class="row">
                                    <div class="col-xs-7 col-md-10 change_align">
                                        <strong>{l s='Order amount tax incl.' mod='easycheckout'}</strong>
                                    </div>
                                    <div class="col-xs-5 col-md-2 table_content">
                                        <strong style="font-size: 18px;">{$total}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/block}