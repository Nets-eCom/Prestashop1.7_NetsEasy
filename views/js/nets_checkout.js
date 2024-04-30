$(document).ready(function () {
    
    var frame = document.getElementById('nets-checkout-iframe');
    var payment_option = document.querySelectorAll('input[type=radio][name="payment-option"]');
    var selected_payment = $('input[type=radio][name="payment-option"]:checked').attr("data-module-name");
    
    if (selected_payment == "netseasy") { 
        $('#conditions-to-approve').css("display", "none"); 
        $('#payment-confirmation').css("display", "none");
    } else if (get_payment_options(selected_payment)) {        
        $('#conditions-to-approve').css("display", "none"); 
        $('#payment-confirmation').css("display", "none");
    }

    if (checkCookie('nets_payment_selected') && !checkCookie('single_timer')) {
        deleteCookie("nets_payment_selected");
        deleteCookie("split_type");
    }
    
    // append Iframe to selected radio if single payment
    if (payment_option.length == 1 && (selected_payment == "netseasy" || get_payment_options(selected_payment)) ) {
        if(!checkCookie('single_timer'))
        {
            setCookie('nets_payment_selected', true, 1);
            if(get_payment_options(selected_payment)) {
                setCookie("split_type", selected_payment, 1);
            }
            setCookie('single_timer', true, 1);
            if (frame) {
                frame.style.display = 'none';
            }                             
            $('#conditions-to-approve').css("display", "none");
            $('#conditions-to-approve').submit();
        }
    } else {
        deleteCookie("single_timer");
    }
    // append Iframe to selected radio if multiple payment
    if (payment_option.length != 0) {
        for (var i = 0; i < payment_option.length; i++) {
            payment_option[i].addEventListener('change', function (e) {
                var name = e.srcElement.getAttribute('data-module-name');
                if (name == "netseasy") { 
                    setCookie('nets_payment_selected', true, 1);
                    deleteCookie("split_type");
                    if (frame) {
                        frame.style.display = 'none';
                    }                             
                    $('#conditions-to-approve').css("display", "none");
                    $('#conditions-to-approve').submit(); 
                } else if (get_payment_options(name)) {
                    setCookie('nets_payment_selected', true, 1);
                    setCookie("split_type", name, 1);
                    if (frame) {
                        frame.style.display = 'none';
                    }     
                    $('#conditions-to-approve').css("display", "none");
                    $('#conditions-to-approve').submit();
                } else {
                    if (checkCookie('nets_payment_selected')) {
                        deleteCookie("nets_payment_selected");
                        deleteCookie("split_type");
                    }
                    if (frame) {
                        frame.style.display = 'none';
                    }
                    $('#conditions-to-approve').css("display", "block"); 
                    $('#payment-confirmation').css("display", "block"); 
                }
            }, false);
        }
    }

    var default_selected = $('input[type=radio][name="payment-option"]:checked').attr("data-module-name");
    $('#netseasy_payment_container').appendTo($('input[type=radio][data-module-name="' + default_selected + '"]').closest('.payment-option'));

    prestashop.on('updatedCart', function (event) {
        var netseasy_selected = $("[data-module-name='netseasy']").prop('checked');
        var selected = $('input[type=radio][name="payment-option"]:checked').attr("data-module-name");
        if (netseasy_selected && typeof checkoutOptions != "undefined") {
            setCookie('nets_payment_selected', true, 1);
        }
        if (get_payment_options(selected) && typeof checkoutOptions != "undefined") {
            setCookie("split_type", selected, 1);
            setCookie('nets_payment_selected', true, 1);
        }
    });

});

var getUrlParameter = function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;
    for (i = 0; i < sURLVariables.length; i++) {
        sParameterName = sURLVariables[i].split('=');
        if (sParameterName[0] === sParam) {
            return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
        }
    }
    return false;
};

function setCookie(cname, cvalue, exdays) {
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}
function getCookie(cname) {
    let name = cname + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}
function checkCookie(name) {
    let cookie = getCookie(name);
    if (cookie != "") {
        return true;
    } else {
        return false;
    }
}
function deleteCookie(name) {
    document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}
payment_options = ['NETS_CARD', 'NETS_MOBILEPAY', 'NETS_VIPPS', 'NETS_SWISH', 'NETS_SOFORT', 'NETS_TRUSTLY', 'NETS_AFTERPAY_INVOICE', 'NETS_AFTERPAY_INSTALLMENT', 'NETS_RATEPAY_INSTALLMENT', 'NETS_PAYPAL'];

function get_payment_options(pay_method) {
    return payment_options.includes(pay_method);
}
