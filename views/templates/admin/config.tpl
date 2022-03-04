<form id="module_form" class="defaultForm form-horizontal" method="post" enctype="multipart/form-data" novalidate="" autocomplete="off">
    <input type="hidden" name="submitNetsModule" value="1">
    <div class="panel" id="fieldset_0">

        <div class="panel-heading">
            <i class="icon-cogs"></i>{l s="configuration" mod="netseasy"}                    
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
                    <input type="text" name="NETS_LIVE_CHECKOUT_KEY" id="NETS_LIVE_CHECKOUT_KEY" value="" class="" value="{$NETS_LIVE_CHECKOUT_KEY} "placeholder="live-checkout-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                     <p class="error text-danger">{l s="nets_live_checkout_key_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="production_secret_key" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_LIVE_SECRET_KEY" id="NETS_LIVE_SECRET_KEY" value="" class="" value="{$NETS_LIVE_SECRET_KEY} "placeholder="live-secret-key-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
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
                <label class="control-label col-lg-4">{l s="terms_url" d="Modules.Netseasy.Config"}</label>
                <div class="col-lg-4">
                    <input type="text" name="NETS_TERMS_URL" id="NETS_TERMS_URL" value="{$NETS_TERMS_URL}" class="">
                    <p class="error text-danger">{l s="nets_terms_url_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-4">{l s="merchant_terms_url" d="Modules.Netseasy.Config"}</label>
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
                    <input type="text" name="NETS_WEBHOOK_AUTHORIZATION" onkeyup="this.value=this.value.replace(/[^a-z0-9-]/gi, '');" id="NETS_WEBHOOK_AUTHORIZATION" value="{$NETS_WEBHOOK_AUTHORIZATION}"  class="required">
                    <p class="error text-danger">{l s="nets_webhook_authorization_error" d="Modules.Netseasy.Config"}</p>
                </div>
            </div>
        <!-- /webhook Config end-->

        </div><!-- /.form-wrapper -->

       
        <div class="panel-footer">
            <button type="submit" value="1" id="module_form_submit_btn" name="submitNetsModule" class="btn btn-default pull-right">
            <i class="process-icon-save"></i> 
            {l s="save" d="Modules.Netseasy.Config"}
            </button>
        </div>
    </div>
</form>