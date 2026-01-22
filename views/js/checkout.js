/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
$(document).ready(function () {
    var place_button = document.querySelectorAll('#payment-confirmation button[type="submit"]');
    var frame = document.getElementById('nets-checkout-iframe');
    var payment_option = document.querySelectorAll('input[type=radio][name="payment-option"]');
    var promo_code_applied = getUrlParameter('updatedTransaction');
    
    if (checkCookie('nets_payment_selected')) {
        deleteCookie("nets_payment_selected");
    }
    place_button[0].addEventListener('click', function (e) {
        var disabled = $('#payment-confirmation button[type="submit"]').hasClass('disabled');
        if (!disabled) {
            var name = $('input[type=radio][name="payment-option"]:checked').attr("data-module-name");
            if (name == "Nets Payment") {
                setCookie('nets_payment_selected', true, 1);
                if (frame) {
                    frame.style.display = '';
                }
            } else {
                if (checkCookie('nets_payment_selected')) {
                    deleteCookie("nets_payment_selected");
                }
                if (frame) {
                    frame.style.display = 'none';
                }
            }
        }
    }, false);
    for (var i = 0; i < payment_option.length; i++) {
        payment_option[i].addEventListener('change', function (e) {
            var name = e.srcElement.getAttribute('data-module-name');
            if (name == "Nets Payment") {
                if (frame) {
                    frame.style.display = '';
                }
            } else {
                if (frame) {
                    frame.style.display = 'none';
                }
            }
        }, false);
    }
    prestashop.on('updatedCart', function (event) {
        var disabled = $(place_button).hasClass('disabled');
        var netseasy_selected = $("[data-module-name='Nets Payment']").prop('checked');
        if (netseasy_selected && disabled == false) {
            setCookie('nets_payment_selected', true, 1);
        }
    });
    
    var container = $('input[type=radio][data-module-name="Nets Payment"]').closest('.payment-option');
    $('#netseasy_payment_container').appendTo(container);
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