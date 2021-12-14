<?php
/**
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
*/

class Cart extends CartCore
{
    public function getPackageList($flush = true)
    {
        return parent::getPackageList($flush);
    }

    public function getDeliveryOption($default_country = null, $dontAutoSelectOptions = false, $use_cache = false)
    {
        return parent::getDeliveryOption($default_country, $dontAutoSelectOptions, $use_cache);
    }
    
    public function getDeliveryOptionList(Country $default_country = null, $flush = true)
    {
        return parent::getDeliveryOptionList($default_country, $flush);
    }
}
