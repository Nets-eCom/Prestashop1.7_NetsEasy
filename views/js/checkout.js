let pw_postalCode = '';
let pw_countryCode = '';
let PS_PURCHASE_FAILED = 'PS_PURCHASE_FAILED';

$(document).ready(function()
{
    prestashop.on(
        'updateCart', function(event) {
            ajaxPause(1);
            updateEasyAndDelivery();
        }
    );
    
    $("#message").on('click', function() {
        $("#message_container").slideToggle();
        $("#message h1").toggleClass("easy-trigger--inactive");
    });
    
    $("#giftwrapping").on('click', function() {
        $("#giftwrapping_container").slideToggle();
        $("giftwrapping h1").toggleClass("easy-trigger--inactive");
    });
    
    $("#savemessagebutton").on('click', function() {
        var order_message = $("#order_message").val();
        changeOrderMessage(order_message);
    });
    
    $("#savegiftbutton").on('click', function() {
        var gift_message = $("#gift_message").val();
        changeGiftMessage(gift_message);
    });
});

function updateEasyAndDelivery() {
    updateDeliveryList();
    updateIframe();
    checkProductsAndCarriers();
}

function checkProductsAndCarriers()
{
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        dataType: 'json',
        data: '&ajax=1'
            +'&checkProductsAndCarriers',
        success: function(data) {
            var return_status_carrier = data.return_status_carrier;
            var return_status_product = data.return_status_product;
            if (return_status_carrier == 'NOK' || return_status_product == 'NOK') {
                $('#easy-complete-checkout').hide();
            } else {
                $('#easy-complete-checkout').show();
            }
            
            if (return_status_carrier == 'NOK') {
                $('#no_available_easy_carriers').show();
            } else {
                $('#no_available_easy_carriers').hide();
            }
            if (return_status_product == 'NOK') {
                $('#no_available_easy_products').show();
            } else {
                $('#no_available_easy_products').hide();
            }
        }
    });
}

function updateDeliveryList()
{
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        data: 'ajax=1' + '&get_delivery_html',
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            $('#pwdc_deliveryoptions').html(data);
        }
    });  
}


function onDeliveryClick(address, key, id)
{
    if ($('#' + id).hasClass('selected') == false) {
        ajaxPause(1);
        $('li.easy-sel-list__item').removeClass('selected');
        $('#' + id).addClass('selected');
        changeDeliveryOption(address, key);
    }
}

function ajaxPause(state)
{
    if (state == 1) {
        $('#pwc-full-loader').show();
        checkout.freezeCheckout();
        $('#dynamic_cart_row').addClass('easy_disable');
        $('#pwdc_deliveryoptions').addClass('easy_disable');
        $('#pwdc_messages').addClass('easy_disable');
    } else if (state == 0) {
        $('div.shopping_cart').removeClass('easy_disable');
        $('#dynamic_cart_row').removeClass('easy_disable');
        $('#pwdc_deliveryoptions').removeClass('easy_disable');
        $('#pwdc_messages').removeClass('easy_disable');
        $('#pwc-full-loader').hide(); 
        checkout.thawCheckout();
    }
}

function changeDeliveryOption(address, key)
{
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        dataType: 'json',
        data: 'ajax=1' + '&change_delivery_option&new_delivery_option[' + address + ']=' + key,
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            updatePrestaCart();
        }
    });
}

function changeOrderMessage(order_message)
{
    ajaxPause(1);
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        dataType: 'json',
        data: 'ajax=1' + '&save_order_message' + '&message=' + encodeURI(order_message),
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            $("#order_message").val(data.message);
            if ($("#order_message").val() == '') {
                $("#message_container").fadeToggle();
                $("#message").addClass("easy-trigger--inactive");
            }
            updatePrestaCart();
        }
    });
}

function changeGift()
{
    ajaxPause(1);
    var gift = 0;
    var message = '';
    
    if ($('#gift').is(":checked")) {
        gift = 1;
        message = $("#gift_message").val();  
    }
    
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        dataType: 'json',
        data: 'ajax=1'
            +'&change_gift'
            +'&gift=' + gift
            +'&gift_message=' + encodeURI(message),
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            $("#gift_message").val(data.message);
            if (data.gift == 0) {
                $("#giftwrapping_container").fadeToggle();
                $("#giftwrapping").addClass("easy-trigger--inactive");
            }
            updatePrestaCart();
        }
    });
}

function changeGiftMessage(message)
{
    ajaxPause(1);
    gift = 0;
    if ($('#gift').is(":checked")) {
        gift = 1; 
    }
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        dataType: 'json',
        data: 'ajax=1'
            +'&change_gift_message'
            +'&gift=' + gift
            +'&gift_message=' + encodeURI(message),
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            $("#gift_message").val(data.message);
            if (data.gift == 1) {
                $("#uniform-gift span").addClass('checked');
                $('#gift').attr('checked', 'checked');

            }
            updatePrestaCart();
        }
    });
}

function updateCartSummary()
{
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        data: 'ajax=1' + '&get_summary_html',
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            }
            $('#pwdc_cart_summary').html(data);
            pwpcCartReady = 1;
            ajaxPause(0);
        }
    });
    if (window.ajaxCart !== undefined) {
        ajaxCart.refresh();
    }
}

function updateIframe()
{
    $.ajax({
        type: 'GET',
        url: pwdc_checkout_url,
        async: true,
        cache: false,
        data: 'ajax=1' + '&update_easy_iframe',
        success: function(data) {
            if (data == 0 || data == PS_PURCHASE_FAILED) {
                location.reload();
            } else {
                ajaxPause(0);
            }
        }
    });
}

function updatePrestaCart() {
    prestashop.emit('updateCart', {reason: 'orderChange'});
}

checkout.on('payment-completed', function(response) {
    hideDynamicContent();
    $('#waiting-for-redirect').show();
    window.location = confirmation_url;
});

checkout.on('pay-initialized', function(response) {
    freezeCheckoutForSwish();
});


function freezeCheckoutForSwish()
{
    $('#dynamic_cart_row').addClass('easy_disable');
    $('#pwdc_deliveryoptions').addClass('easy_disable');
    $('#pwdc_messages').addClass('easy_disable');
    checkout.send('payment-order-finalized', true);
}

function hideDynamicContent() {
    $("#dynamic_changes").hide();
    $(".easy-iframe-grid").hide();
    $(".easy-iframe-grid").hide();
    $("#dynamic_changes").empty();    
    $("#dynamic_cart_row").hide();
    $("#dynamic_cart_row").empty();   
    $("#ecster-pay-ctr").empty();   
}

function loading(state) {
    if (state == 1) {
        $('#pwc-full-loader').show();
    } else {
        $('#pwc-full-loader').hide();
    }
}

checkout.on('address-changed', function(address) {
	pw_postalCode = address.postalCode;
	pw_countryCode = address.countryCode;
    if (address.countryCode) {
        if (easy_current_country != address.countryCode) {
            loading(1);
            window.location.replace(easycheckout_url + '?changeEasyCountry=' + address.countryCode);
        }
    }
});