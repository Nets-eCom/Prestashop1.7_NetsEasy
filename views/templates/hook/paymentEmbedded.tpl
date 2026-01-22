{**
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
 *}
{if $module == 'netseasy'}
    {if $debugMode}
        <div class="col-md-12">
            <pre class="nets">{$returnUrl|print_r}{$datastring|print_r}</pre>
        </div>
    {/if}
    <div id="netseasy_payment_container" class="content">
        <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
        <script type="text/javascript" src="{$checkout.url}"></script>
        <div id="nets-complete-checkout"></div>


        <script>
            var checkoutOptions = {
                checkoutKey: "{$checkout.checkoutKey}",
                paymentId: "{$paymentId}",
                containerId: "nets-complete-checkout",
                language: "{$lang}"
            };

            var checkout = new Dibs.Checkout(checkoutOptions);

            checkout.on('payment-completed', function (response) {
                window.location = '{$returnUrl}&paymentid={$paymentId}';
                    });
                    $(document).ready(function () {
                        $('input[type=radio][data-module-name="{$payment_split_type}"]').prop('checked', true);
                        $('#payment-confirmation button[type="submit"]').removeClass('disabled');
                    });
        </script>
    </div>
{/if}
