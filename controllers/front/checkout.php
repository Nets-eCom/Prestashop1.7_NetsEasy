  <?php
use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Core\Checkout\TermsAndConditions;
use PrestaShop\PrestaShop\Core\Foundation\Templating\RenderableProxy;

class netseasyCheckoutModuleFrontController extends ModuleFrontController
{
    public $checkoutWarning = false;

    /**
     * @var CheckoutProcess
     */
    protected $checkoutProcess;

    /**
     * @var CartChecksum
     */
    protected $cartChecksum;

    public function setMedia()
    {
        parent::setMedia();
    }

    public function initcontent()
    {
        parent::initcontent();
    }

    public function postProcess()
    {
        $NetsEasy = new Netseasy();
        if ($NetsEasy->active && Configuration::get('NETS_INTEGRATION_TYPE') === 'EMBEDDED') {
            $this->cartChecksum = new CartChecksum(new AddressChecksum());
        
            /**
             * Get current cart object from session
             */
            /*$cart = $this->context->cart;
            $presentedCart = $this->cart_presenter->present($cart, true);
            if (count($presentedCart['products']) <= 0 || $presentedCart['minimalPurchaseRequired']) {
                // if there is no product in current cart, redirect to cart page
                $cartLink = $this->context->link->getPageLink('cart');
                Tools::redirect($cartLink);
            }

            $product = $this->context->cart->checkQuantities(true);
            if (is_array($product)) {
                // if there is an issue with product quantities, redirect to cart page
                $cartLink = $this->context->link->getPageLink('cart', null, null, ['action' => 'show']);
                Tools::redirect($cartLink);
            }*/

            $translator = $this->getTranslator();
            $session = $this->getCheckoutSession();
            $this->checkoutProcess = $this->buildCheckoutProcess($session, $translator);
            $this->restorePersistedData($this->checkoutProcess);
            $this->checkoutProcess->handleRequest(
                Tools::getAllValues()
            );

            $payload = $NetsEasy->createRequestObject($this->context->cart->id);
            $url = $NetsEasy->getApiUrl()['backend'];
            $checkOut = array('url' => $NetsEasy->getApiUrl()['frontend'],
                'checkoutKey' => $NetsEasy->getApiKey()['checkoutKey'],
            );

            $response  = $NetsEasy->MakeCurl($url, $payload);
            if ($response) {
                $this->context->smarty->assign([
                    'paymentId' => $response->paymentId,
                ]);
            } else {
                print_r($response);
            }

            $this->checkoutProcess
                ->setNextStepReachable()
                ->markCurrentStep()
                ->invalidateAllStepsAfterCurrent();
            
            $this->context->smarty->assign([
                'checkout_process' => new RenderableProxy($this->checkoutProcess),
                'checkout' => $checkOut,
                'lang' =>$this->context->language->language_code,
                'tos_cms' => $this->getDefaultTermsAndConditions(),
                'returnUrl' => $this->context->link->getModuleLink($NetsEasy->name, 'return', array('id_cart'=> $this->context->cart->id)),
            ]);

            return $this->setTemplate('module:netseasy/views/templates/front/checkout.tpl');
        } else {
            Tools::redirect('index.php?controller=order&step=1');
        }
    }

    /**
     * Return default TOS link for checkout footer
     *
     * @return string|bool
     */
    protected function getDefaultTermsAndConditions()
    {
        $cms = new CMS((int) Configuration::get('PS_CONDITIONS_CMS_ID'), $this->context->language->id);

        if (!Validate::isLoadedObject($cms)) {
            return false;
        }

        $link = $this->context->link->getCMSLink($cms, $cms->link_rewrite, (bool) Configuration::get('PS_SSL_ENABLED'));

        $termsAndConditions = new TermsAndConditions();
        $termsAndConditions
            ->setText(
                '[' . $cms->meta_title . ']',
                $link
            )
            ->setIdentifier('terms-and-conditions-footer');

        return $termsAndConditions->format();
    }

    /**
     * Persists cart-related data in checkout session.
     *
     * @param CheckoutProcess $process
     */
    protected function saveDataToPersist(CheckoutProcess $process)
    {
        $data = $process->getDataToPersist();
        $addressValidator = new AddressValidator($this->context);
        $customer = $this->context->customer;
        $cart = $this->context->cart;

        $shouldGenerateChecksum = false;

        if ($customer->isGuest()) {
            $shouldGenerateChecksum = true;
        } else {
            $invalidAddressIds = $addressValidator->validateCartAddresses($cart);
            if (empty($invalidAddressIds)) {
                $shouldGenerateChecksum = true;
            }
        }

        $data['checksum'] = $shouldGenerateChecksum
            ? $this->cartChecksum->generateChecksum($cart)
            : null;

        Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'cart SET checkout_session_data = "' . pSQL(json_encode($data)) . '"
                WHERE id_cart = ' . (int) $cart->id
        );
    }

    /**
     * @return CheckoutSession
     */
    public function getCheckoutSession()
    {
        $deliveryOptionsFinder = new DeliveryOptionsFinder(
            $this->context,
            $this->getTranslator(),
            $this->objectPresenter,
            new PriceFormatter()
        );

        $session = new CheckoutSession(
            $this->context,
            $deliveryOptionsFinder
        );

        return $session;
    }

    /**
     * Restores from checkout session some previously persisted cart-related data.
     *
     * @param CheckoutProcess $process
     */
    protected function restorePersistedData(CheckoutProcess $process)
    {
        $cart = $this->context->cart;
        $customer = $this->context->customer;
        $rawData = Db::getInstance()->getValue(
            'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int) $cart->id
        );
        $data = json_decode($rawData, true);
        if (!is_array($data)) {
            $data = [];
        }

        $addressValidator = new AddressValidator();
        $invalidAddressIds = $addressValidator->validateCartAddresses($cart);
        $checksum = null;
        // Build the currently selected address' warning message (if relevant)
        if (!$customer->isGuest() && !empty($invalidAddressIds)) {
            $this->checkoutWarning['address'] = [
                'id_address' => (int) reset($invalidAddressIds),
                'exception' => $this->trans(
                    'Your address is incomplete, please update it.',
                    [],
                    'Shop.Notifications.Error'
                ),
            ];
             $checksum = null;
        } else {
            #Tools::dieObject($this->cartChecksum);
            $checksum = $this->cartChecksum->generateChecksum($cart);
        }

        // Prevent check for guests
        if ($customer->id) {
            // Prepare all other addresses' warning messages (if relevant).
            // These messages are displayed when changing the selected address.
            $allInvalidAddressIds = $addressValidator->validateCustomerAddresses($customer, $this->context->language);
            $this->checkoutWarning['invalid_addresses'] = $allInvalidAddressIds;
        }

        if (isset($data['checksum']) && $data['checksum'] === $checksum) {
            $process->restorePersistedData($data);
        }
    }

    /**
     * @param CheckoutSession $session
     * @param $translator
     *
     * @return CheckoutProcess
     */
    protected function buildCheckoutProcess(CheckoutSession $session, $translator)
    {
        $checkoutProcess = new CheckoutProcess(
            $this->context,
            $session
        );

            $checkoutProcess
            ->addStep(new CheckoutPersonalInformationStep(
                $this->context,
                $translator,
                $this->makeLoginForm(),
                $this->makeCustomerForm()
            ))
            ->addStep(new CheckoutAddressesStep(
                $this->context,
                $translator,
                $this->makeAddressForm()
            ));

            if (!$this->context->cart->isVirtualCart()) {
                $checkoutDeliveryStep = new CheckoutDeliveryStep(
                    $this->context,
                    $translator
                );

                $checkoutDeliveryStep
                    ->setRecyclablePackAllowed((bool) Configuration::get('PS_RECYCLABLE_PACK'))
                    ->setGiftAllowed((bool) Configuration::get('PS_GIFT_WRAPPING'))
                    ->setIncludeTaxes(
                        !Product::getTaxCalculationMethod((int) $this->context->cart->id_customer)
                        && (int) Configuration::get('PS_TAX')
                    )
                    ->setDisplayTaxesLabel((Configuration::get('PS_TAX') && !Configuration::get('AEUC_LABEL_TAX_INC_EXC')))
                    ->setGiftCost(
                        $this->context->cart->getGiftWrappingPrice(
                            $checkoutDeliveryStep->getIncludeTaxes()
                        )
                    );

                $checkoutProcess->addStep($checkoutDeliveryStep);
            }

        return $checkoutProcess;
    }
}