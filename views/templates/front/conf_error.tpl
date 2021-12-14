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
    <div class="alert alert-warning" role="alert">
        <p>
            {l s='We are sorry, but the payment could not be processed right now. Please contact the customer service, you\'re reference is %s' sprintf=[$paymentId] mod='easycheckout'}
        </p>
    </div>
{/block}