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

<div>
	<ul class="alert alert-danger">
        <li>{l s='Ops, an error occurred. Could not create a paymentID, please try refreshing the page' mod='easycheckout'}</li>
    </ul>
</div>
{/block}
