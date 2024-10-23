$(document).ready(function () {
    $('.payment_method_country').select2();
    $('.payment_method_currency').select2();

    $('.error').css('display', 'none');
    $('input').on('keyup', function () {
        $(this).next('.error').css('display', 'none');
    });

    $('input[name="NETS_PAYMENT_SPLIT"]').on('click', function () {
        pay_split_check = $('#NETS_PAYMENT_SPLIT_on').is(':checked');
        if(pay_split_check) {
            $('.pay_split').css('display', 'block');
        } else {
            $('.pay_split').css('display', 'none');
        }
    });

    payment_split = $('#NETS_PAYMENT_SPLIT_on').prop('checked');
    if(payment_split == true) {
        $('.pay_split').css('display', 'block');
    } else {
        $('.pay_split').css('display', 'none');
    }

    $('#module_form').on('submit', function (e) {
        e.preventDefault();
        merchant_id = $.trim($('#NETS_MERCHANT_ID').val());
        merchant_email_id = $.trim($('#NETS_MERCHANT_EMAIL_ID').val());
        payment_name = $.trim($('#NETS_PAYMENT_NAME').val());
        test_mode = $('#NETS_TEST_MODE_on').prop('checked');
        test_checkout_key = $.trim($('#NETS_TEST_CHECKOUT_KEY').val());
        test_secret_key = $.trim($('#NETS_TEST_SECRET_KEY').val());
        live_checkout_key = $.trim($('#NETS_LIVE_CHECKOUT_KEY').val());
        live_secret_key = $.trim($('#NETS_LIVE_SECRET_KEY').val());
        terms_url = $.trim($('#NETS_TERMS_URL').val());
        merchant_terms_url = $.trim($('#NETS_MERCHANT_TERMS_URL').val());
        webhook_authorization = $.trim($('#NETS_WEBHOOK_AUTHORIZATION').val());
        if (merchant_id == '') {
            $('#NETS_MERCHANT_ID').focus().next('.error').css('display', 'inline-block');
        } else if (merchant_email_id == '') {
            $('#NETS_MERCHANT_EMAIL_ID').focus().next('.error').css('display', 'inline-block');
        } else if (payment_name == '') {
            $('#NETS_PAYMENT_NAME').focus().next('.error').css('display', 'inline-block');
        } else if (test_mode == true && test_checkout_key == '') {
            $('#NETS_TEST_CHECKOUT_KEY').focus().next('.error').css('display', 'inline-block');
        } else if (test_mode == true && test_secret_key == '') {
            $('#NETS_TEST_SECRET_KEY').focus().next('.error').css('display', 'inline-block');
        } else if (test_mode == false && live_checkout_key == '') {
            $('#NETS_LIVE_CHECKOUT_KEY').focus().next('.error').css('display', 'inline-block');
        } else if (test_mode == false && live_secret_key == '') {
            $('#NETS_LIVE_SECRET_KEY').focus().next('.error').css('display', 'inline-block');
        } else if (terms_url == '' || validateUrl(terms_url) == false) {
            $('#NETS_TERMS_URL').focus().next('.error').css('display', 'inline-block');
        } else if (merchant_terms_url == '' || validateUrl(merchant_terms_url) == false) {
            $('#NETS_MERCHANT_TERMS_URL').focus().next('.error').css('display', 'inline-block');
        } else if (webhook_authorization == '' || validateAUTH(webhook_authorization) == false) {
            $('#NETS_WEBHOOK_AUTHORIZATION').focus().next('.error').css('display', 'inline-block');
        } else {
            $('#module_form')[0].submit();
        }
    })
});

function validateUrl(url, https)   // return true or false.
{
    if (https) {
        var regexp = /(https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
    } else {
        var regexp = /(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
    }
    return regexp.test(url);
}

function validateAUTH(data)   // return true or false.
{
    var regexp = /^[a-zA-Z0-9-]{8,32}$/

    return regexp.test(data);
}
