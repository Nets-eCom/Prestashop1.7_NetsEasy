{if (!empty($customData) && ($customData['status'] == "00" || $customData['status'] == "11"))}
    <script>
        $(document).ready(function () {
            $('.notification_message h3').css('margin-left', '0');
            $('.notification_message h3').css('margin-right', '0');
            $('.notification_message h3').css('margin-top', '0');
            $('.notification_message h3').css('border-bottom', '0');
            $('.notification_message p:first').css('margin-bottom', '20px');

            $('#netseasy-modal').modal('show');
        });
    </script>
    <div class="modal fade" id="netseasy-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">x</button>
                    <h4 class="modal-title">
                        {if ($customData['status'] == "00")}
                            <strong>{l s="Update Notification" d="Modules.Netseasy.Config"}</strong>
                        {/if}
                        {if ($customData['status'] == "11")}
                            <strong>{l s="Success Notification" d="Modules.Netseasy.Config"}</strong>
                        {/if}
                    </h4>
                </div>
                <div class="modal-body">
                    {if ($customData['status'] == "00")}
                        <div class="modal-title notification_message">{{$customData['data']['notification_message']}}</div>
                        <div class="form-group-lg" style="font-size: small;">
                            <label class="form-control-label">{l s="Latest Plugin Version" d="Modules.Netseasy.Config"} : </label> {{$customData['data']['plugin_version']}} version </br>
                            <label class="form-control-label">{l s="Shop Version Compatible" d="Modules.Netseasy.Config"} : </label> {{$customData['data']['shop_version']}} </br>
                            {if !empty($customData['data']['repo_links'])}
                                <label class="form-control-label">{l s="Github Link" d="Modules.Netseasy.Config"} : </label> <a href="{{$customData['data']['repo_links']}}" target="_blank">Click here</a> </br>
                            {/if}
                            {if !empty($customData['data']['tech_site_links'])}
                                <label class="form-control-label">{l s="TechSite Link" d="Modules.Netseasy.Config"} : </label> <a href="{{$customData['data']['tech_site_links']}}" target="_blank">Click here</a>
                            {/if}
                            {if !empty($customData['data']['marketplace_links'])}
                                <label class="form-control-label">{l s="MarketPlace Link" d="Modules.Netseasy.Config"} : </label> <a href="{{$customData['data']['marketplace_links']}}" target="_blank">Click here</a>
                            {/if}
                        </div>
                    {/if}
                    {if ($customData['status'] == "11")}
                        <h4 class="modal-title">{{$customData['data']['notification_message']}}</h4>
                    {/if}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{l s="Ok" d="Modules.Netseasy.Config"}</button>
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->
    <div class="modal-overlay"></div>
{/if}

{if isset($success_nets)}
    <div class="alert alert-success clearfix">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true"><i class="material-icons">close</i></span>
        </button>
        {l s="nets_config_success" d="Modules.Netseasy.Config"}
    </div>
{/if}
{if isset($form_success)}
    <div class="alert alert-success clearfix">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true"><i class="material-icons">close</i></span>
        </button>
        {l s="form_success" d="Modules.Netseasy.Config"}
        {l s="$form_success" d="Modules.Netseasy.Config"}
    </div>
{/if}
<div class="alert alert-info" style="content : î¢Ž">
    <p>Enable <strong>{l s="payment_split" d="Modules.Netseasy.Config"}</strong> in configuration, order to show each enabled payment method as a separate option to select before loading the checkout.</p>
    <p>You can configure each payment method below. This is recommended if you have other payment plugins active and / or do not use our embedded checkout.</p>
    <p>If you don't use any other payment plugin and embedded checkout, we recommend to not use this setting.</p>
</div>
<form id="module_form" class="defaultForm form-horizontal" method="post" enctype="multipart/form-data" novalidate="" autocomplete="off">
    <input type="hidden" name="submitNetsModule" value="1">
    <div class="panel" id="fieldset_0">

        <div class="panel-heading">
            <i class="icon-cogs"></i>{l s="configuration" d="Modules.Netseasy.Config"}                    
        </div>
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="test_mode" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="NETS_TEST_MODE" id="NETS_TEST_MODE_on" value="1" {if $NETS_TEST_MODE == "1"} checked="checked" {/if}>
                        <label for="NETS_TEST_MODE_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                        <input type="radio" name="NETS_TEST_MODE" id="NETS_TEST_MODE_off" value="" {if $NETS_TEST_MODE == ""} checked="checked" {/if}>
                        <label for="NETS_TEST_MODE_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">{l s="test_help" d="Modules.Netseasy.Config"} </p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="payment_split" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="NETS_PAYMENT_SPLIT" id="NETS_PAYMENT_SPLIT_on" value="1" {if $NETS_PAYMENT_SPLIT == "1"} checked="checked" {/if}>
                        <label for="NETS_FRONTEND_DEBUG_MODE_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                        <input type="radio" name="NETS_PAYMENT_SPLIT" id="NNETS_PAYMENT_SPLIT_off" value="" {if $NETS_PAYMENT_SPLIT == ""} checked="checked" {/if}>
                        <label for="NETS_PAYMENT_SPLIT_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="merchant_id" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_MERCHANT_ID" id="NETS_MERCHANT_ID"  class=""  value="{$NETS_MERCHANT_ID}">
                    <p class="error text-danger">{l s="nets_merchant_id_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="merchant_email_id" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_MERCHANT_EMAIL_ID" id="NETS_MERCHANT_EMAIL_ID"  class=""  value="{$NETS_MERCHANT_EMAIL_ID}">
                    <p class="error text-danger">{l s="nets_merchant_email_id_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="payment_name" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_PAYMENT_NAME" id="NETS_PAYMENT_NAME"  class=""  value="{$NETS_PAYMENT_NAME}">
                    <p class="error text-danger">{l s="nets_payment_name_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="test_checkout_key" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_TEST_CHECKOUT_KEY" id="NETS_TEST_CHECKOUT_KEY"  class=""  value="{$NETS_TEST_CHECKOUT_KEY}" placeholder="test-checkout-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <p class="error text-danger">{l s="nets_test_checkout_key_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="test_secret_key" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_TEST_SECRET_KEY" id="NETS_TEST_SECRET_KEY" class="" value="{$NETS_TEST_SECRET_KEY}" placeholder="test-secret-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    <p class="error text-danger">{l s="nets_test_secret_key_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="production_checkout_key" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_LIVE_CHECKOUT_KEY" id="NETS_LIVE_CHECKOUT_KEY" class="" value="{$NETS_LIVE_CHECKOUT_KEY}" placeholder="live-checkout-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                    <p class="error text-danger">{l s="nets_live_checkout_key_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="production_secret_key" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_LIVE_SECRET_KEY" id="NETS_LIVE_SECRET_KEY" class="" value="{$NETS_LIVE_SECRET_KEY}" placeholder="live-secret-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                    <p class="error text-danger">{l s="nets_live_secret_key_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">
                    {l s="integration_type" d="Modules.Netseasy.Config"}
                </label>
                <div class="col-lg-6">
                    <select name="NETS_INTEGRATION_TYPE" class=" fixed-width-xl" id="NETS_INTEGRATION_TYPE">
                        <option value="REDIRECT" {if $NETS_INTEGRATION_TYPE == "REDIRECT"} selected="selected" {/if} >{l s="redirect" d="Modules.Netseasy.Config"}</option>
                        <option value="EMBEDDED"  {if $NETS_INTEGRATION_TYPE == "EMBEDDED"} selected="selected" {/if}>{l s="embedded" d="Modules.Netseasy.Config"}</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="terms_url" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_TERMS_URL" id="NETS_TERMS_URL" value="{$NETS_TERMS_URL}" class="">
                    <p class="error text-danger">{l s="nets_terms_url_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4 required">{l s="merchant_terms_url" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_MERCHANT_TERMS_URL" id="NETS_MERCHANT_TERMS_URL" value="{$NETS_MERCHANT_TERMS_URL}" class="">
                    <p class="error text-danger">{l s="nets_merchant_terms_url_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="auto_capture" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="NETS_AUTO_CAPTURE" id="NETS_AUTO_CAPTURE_on" value="1" {if $NETS_AUTO_CAPTURE == "1"} checked="checked" {/if}>
                        <label for="NETS_AUTO_CAPTURE_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                        <input type="radio" name="NETS_AUTO_CAPTURE" id="NETS_AUTO_CAPTURE_off" value="" {if $NETS_AUTO_CAPTURE == ""} checked="checked" {/if}>
                        <label for="NETS_AUTO_CAPTURE_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="admin_debug_mode" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="NETS_ADMIN_DEBUG_MODE" id="NETS_ADMIN_DEBUG_MODE_on" value="1" {if $NETS_ADMIN_DEBUG_MODE == "1"} checked="checked" {/if}>
                        <label for="NETS_ADMIN_DEBUG_MODE_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                        <input type="radio" name="NETS_ADMIN_DEBUG_MODE" id="NETS_ADMIN_DEBUG_MODE_off" value="" {if $NETS_ADMIN_DEBUG_MODE == ""} checked="checked" {/if}>
                        <label for="NETS_ADMIN_DEBUG_MODE_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="frontend_debug_mode" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-8">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="NETS_FRONTEND_DEBUG_MODE" id="NETS_FRONTEND_DEBUG_MODE_on" value="1" {if $NETS_FRONTEND_DEBUG_MODE == "1"} checked="checked" {/if}>
                        <label for="NETS_FRONTEND_DEBUG_MODE_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                        <input type="radio" name="NETS_FRONTEND_DEBUG_MODE" id="NETS_FRONTEND_DEBUG_MODE_off" value="" {if $NETS_FRONTEND_DEBUG_MODE == ""} checked="checked" {/if}>
                        <label for="NETS_FRONTEND_DEBUG_MODE_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>
            <!-- /webhook Config start-->
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="webhook_url" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_WEBHOOK_URL" id="NETS_WEBHOOK_URL" value="{$NETS_WEBHOOK_URL}" class="">
                    <p class="error text-danger">{l s="nets_webhook_url_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="webhook_authorization" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_WEBHOOK_AUTHORIZATION" onkeyup="this.value = this.value.replace(/[^a-z0-9-]/gi, '');" id="NETS_WEBHOOK_AUTHORIZATION" value="{$NETS_WEBHOOK_AUTHORIZATION}"  class="required">
                    <p class="error text-danger">{l s="nets_webhook_authorization_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <!-- /webhook Config end-->

        </div><!-- /.form-wrapper -->

        <!-- /split payment -->
        <div>

        </div>
        <!-- /split payment -->
        <div class="panel-footer">
            <button type="submit" value="1" id="module_form_submit_btn" name="submitNetsModule" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> 
                {l s="save" d="Modules.Netseasy.Config"}
            </button>
        </div>
    </div>
</form>

<div class="panel pay_split">
    <h3>
        <i class="icon-cogs"></i>
        {l s="Payment_Option" d="Modules.Netseasy.Config"}
    </h3>
    <ul class="nav nav-tabs" id="splitpayment" role="tablist">
        {foreach from=$pay_type_list key=i item=pay_type}
            <li class="nav-item
                {if $i == 0}
                    active
                {/if}"> 
                <a class="nav-link" id="{$pay_type}" data-toggle="tab" href="#{$pay_type}_maintab" role="tab" aria-controls="{$pay_type}" aria-selected="true">{l s="$pay_type" d="Modules.Netseasy.Config"}</a>
            </li>
        {/foreach}
    </ul>
    <div class="tab-content" id="splitpaymentContent">
        {foreach from=$pay_type_list key=i item=pay_type}
            <div class="tab-pane
                 {if $i == 0}
                     active
                 {/if}" id="{$pay_type}_maintab" role="tabpanel" aria-labelledby="{$pay_type}_maintab-tab">
                <form id="{$pay_type}_form" class="defaultForm form-horizontal" method="post" enctype="multipart/form-data" >
                    <input type="hidden" name="submit_{$pay_type}" value="1">
                    <div class="panel a" id="fieldset_0">
                        <div class="panel-heading">
                            {l s="Setting" d="Modules.Netseasy.Config"}
                        </div>
                        <div class="form-wrapper">

                            <div class="form-group">
                                <label class="control-label col-lg-4 required">{l s="Enable " d="Modules.Netseasy.Config"}{l s="{$pay_type}" d="Modules.Netseasy.Config"}</label>
                                <div class="col-lg-8">
                                    <span class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="{$pay_type}_ENABLED" id="{$pay_type}_on" value="1" {if  {${$pay_type}_ENABLED} == "1"} checked="checked" {/if}>
                                        <label for="{$pay_type}_on">{l s="active" d="Modules.Netseasy.Config"}</label>
                                        <input type="radio" name="{$pay_type}_ENABLED" id="{$pay_type}_off" value="" {if  {${$pay_type}_ENABLED} == ""} checked="checked" {/if}>
                                        <label for="{$pay_type}_off">{l s="inactive" d="Modules.Netseasy.Config"}</label>
                                        <a class="slide-button btn"></a>
                                    </span>
                                </div>
                            </div>

                             <div class="form-group">
                                <label class="control-label col-lg-4 required">{l s="payment_name" d="Modules.Netseasy.Config"}</label>
                                <div class="col-lg-4">
                                    <input type="text" name="{$pay_type}_CUSTOM_NAME" id="{$pay_type}_CUSTOM_NAME" value="{${$pay_type}_CUSTOM_NAME}" class="required" required />
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-lg-4">
                                    {l s="Select_Country" d="Modules.Netseasy.Config"}
                                    {l s="{$pay_type}" d="Modules.Netseasy.Config"}
                                </label>
                                <div class="col-lg-8">
                                    <select name="{$pay_type}_COUNTRY_IDS[]" class="{$pay_type}_multiple_select2 payment_method_country fixed-width-xl" id="{$pay_type}_COUNTRY_IDS[]" multiple="multiple">
                                        {foreach from=$country_list key=arrayIndex item=record}
                                            <option value="{$arrayIndex}"
                                                    {assign var="select_country_ids" value=","|explode:${$pay_type}_COUNTRY_IDS}
                                                    {if (is_array($select_country_ids)) }
                                                        {if (in_array($arrayIndex,$select_country_ids)) }
                                                            selected
                                                        {/if}
                                                    {/if}
                                                    >   {$record.name}</option>
                                        {/foreach}
                                    </select>
                                    <p class="help-block">
                                        {l s="Select_Country_Info" d="Modules.Netseasy.Config"}                                        
                                    </p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-4">
                                    {l s="Select_Currency" d="Modules.Netseasy.Config"}
                                    {l s="{$pay_type}" d="Modules.Netseasy.Config"}
                                </label>
                                <div class="col-lg-8">
                                    <select name="{$pay_type}_CURRENCY_IDS[]" class="{$pay_type}_multiple_select2 payment_method_currency fixed-width-xl" id="{$pay_type}_CURRENCY_IDS[]" multiple="multiple">
                                        {foreach from=$currency_list item=record}
                                            <option value="{$record.id}"
                                                    {assign var="select_currency_ids" value=","|explode:${$pay_type}_CURRENCY_IDS}
                                                    {if (is_array($select_currency_ids)) }
                                                        {if (in_array($record.id,$select_currency_ids)) }
                                                            selected
                                                        {/if}
                                                    {/if}
                                                    >   {$record.name}</option>
                                        {/foreach}
                                    </select>
                                    <p class="help-block">
                                        {l s="Select_Currency_Info" d="Modules.Netseasy.Config"}                                        
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer">
                            <button type="submit" value="1" id="{$pay_type}_submit_btn" name="submit_{$pay_type}" class="btn btn-default pull-right">
                                <i class="process-icon-save"></i>                                
                                {l s="save" d="Modules.Netseasy.Config"}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        {/foreach}
    </div>
</div>
