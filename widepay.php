<?php
/**
 * Widepay
 *
 * API docs can be found at: https://widepay.github.io/api/#cobranca-gerando
 *
 * @package blesta
 * @subpackage blesta.components.gateways.widepay
 * @copyright Copyright (c) 2019, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Widepay extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        // Load the cWatch API
        Loader::load(dirname(__FILE__) . DS . 'api' . DS . 'widepay_api.php');

        // Load configuration required by this gateway
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this module
        Language::loadLang('widepay', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        // Load the view into this object, so helpers can be automatically add to the view
        $this->view = new View('settings', 'default');
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'nonmerchant' . DS . 'widepay' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);
        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'wallet_id' => [
                'format' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Widepay.!error.wallet_id.format', true)
                ]
            ],
            'wallet_token' => [
                'format' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Widepay.!error.wallet_token.format', true)
                ]
            ]
        ];
        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);

        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['wallet_id', 'wallet_token'];
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }


    /**
     * Returns all HTML markup required to render an authorization and capture payment form.
     *
     * @param array $contact_info An array of contact info including:
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
        // Load the models required
        Loader::loadModels($this, ['Clients']);

        // Load the helpers required
        Loader::loadHelpers($this, ['Html']);

        if (!empty($_POST)) {
            // Load the models required
            Loader::loadModels($this, ['Contacts']);

            // Load library methods
            $api = $this->getApi();

            // Force 2-decimal places only
            $amount = number_format($amount, 2, '.', '');

            // Get client data
            $client = $this->Clients->get($contact_info['client_id']);
            $client->fields = $this->Clients->getCustomFieldValues($contact_info['client_id']);

            $cpf_cnpj = '';
            $entity_type = 'Física';
            foreach ($client->fields as $field) {
                if (strtolower($field->name) == 'cpf/cnpj') {
                    $cpf_cnpj = $field->value;
                }

                if (strtolower($field->name) == 'entity type') {
                    $entity_type = $field->value;
                }
            }

            // Get client phone number
            $contact_numbers = $this->Contacts->getNumbers($client->contact_id);

            $client_phone = '';
            foreach ($contact_numbers as $contact_number) {
                switch ($contact_number->location) {
                    case 'home':
                        // Set home phone number
                        if ($contact_number->type == 'phone') {
                            $client_phone = $contact_number->number;
                        }
                        break;
                    case 'work':
                        // Set work phone/fax number
                        if ($contact_number->type == 'phone') {
                            $client_phone = $contact_number->number;
                        }
                        // No break?
                    case 'mobile':
                        // Set mobile phone number
                        if ($contact_number->type == 'phone') {
                            $client_phone = $contact_number->number;
                        }
                        break;
                }
            }

            if (!empty($client_phone)) {
                $client_phone = preg_replace('/[^0-9]/', '', $client_phone);
            }

            // Build the payment request
            $notification_url = Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id')
                . '/widepay/?client_id=' . $contact_info['client_id'];
            $form_type = 'Cartão'; // This should be customizable
            $params = [
                'forma' => $form_type, // Form type (card or ticket)
                'cliente' => $this->Html->concat(
                    ' ',
                    $this->ifSet($contact_info['first_name']),
                    $this->ifSet($contact_info['last_name'])
                ),
                'pessoa' => $entity_type, // Check custom fields
                'email' => $this->ifSet($client->email),
                'telefone' => $this->ifSet($client_phone),
                'itens' => [],
                'notificacao' => $this->ifSet($notification_url),
            ];

            if ($form_type == 'Boleto') {
                $params['vencimento'] = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +1 day')); // Figure out what this is
            }

            if ($entity_type == 'Física') {
                $params['cpf'] = $cpf_cnpj; // Check custom fields
            } else {
                $params['cnpj'] = $cpf_cnpj; // Check custom fields
            }

            // Set all invoices to pay
            if (isset($invoice_amounts) && is_array($invoice_amounts)) {
                foreach ($invoice_amounts as $invoice) {
                    $params['itens'][] = [
                        'descricao' => $invoice['id'], // Description
                        'valor' => $invoice['amount'], // Value
                    ];
                }
            }

            $this->log($this->ifSet($_SERVER['REQUEST_URI']), serialize($params), 'input', true);

            // Send the request to the api
            $request = $api->createCharge($params);
            $errors = $request->errors();
            if (empty($errors)) {
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), $request->raw(), 'output', true);

                $charge_response = $request->response();

                // Redirect the use to Wide Pay to finish payment
                $this->redirectToUrl($charge_response->link);
            } else {
                // The api has been responded with an error, set the error
                $this->log($this->ifSet($_SERVER['REQUEST_URI']), $request->raw(), 'output', false);
                $this->Input->setErrors(
                    ['api' => ['response' => $request->errors()]]
                );

                return null;
            }
        }

        // Build the payment form
        return $this->buildForm();
    }

    /**
     * Builds the HTML form.
     *
     * @return string The HTML form
     */
    private function buildForm()
    {
        $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        return $this->view->fetch();
    }

    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        $api = $this->getApi();

        // The api has been responded with an error, set the error
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), json_encode($post), 'input', true);

        // Get the transaction details
        $charge_response = $api->getNotificationCharge(isset($post['notificacao']) ? $post['notificacao'] :  '');

        // Log the Wide Pay response
        $errors = $charge_response->errors();
        $this->log($this->ifSet($_SERVER['REQUEST_URI']), $charge_response->raw(), 'output', empty($errors));

        $response = $charge_response->response();

        $status = empty($errors) ? 'approved' : 'error';

        if ($this->ifSet($response->cobranca->status)) {
            switch ($response->cobranca->status) {
                case 'Aguardando':
                case 'Em análise':
                    $status = 'pending';
                    break;
                case 'Estornado':
                    $status = 'refunded';
                    break;
                case 'Recebido':
                case 'Recebido manualmente':
                    $status = 'approved';
                    break;
                case 'Recusado':
                case 'Cancelado':
                case 'Contestado':
                    $status = 'declined';
                    break;
                case 'Vencido':
                    $status = 'void';
                    break;
            }
        }

        return [
            'client_id' => $get['client_id'],
            'amount' => $this->ifSet($response->cobranca->valor, 0),
            'currency' => 'BRL',
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response->cobranca->id),
            'invoices' => $this->unserializeInvoices($this->ifSet($response->cobranca->itens, []))
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    {
        // Get client id
        $client_id = $this->ifSet($get['client_id']);

        return [
            'client_id' => $client_id,
            'amount' => null,
            'currency' => null,
            'status' => 'approved',
            'reference_id' => null,
            'transaction_id' => null,
            'invoices' => null
        ];
    }

    /**
     * Unserializes a string of invoice info into an array.
     *
     * @param array $items A list of items from Wide Pay
     * @return array A numerically indexed array invoices info including:
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices(array $items)
    {
        $invoices = [];
        foreach ($items as $item) {
            $invoices[] = ['id' => $item->descricao, 'amount' => $item->valor];
        }

        return $invoices;
    }

    /**
     * Loads the given API if not already loaded
     *
     * @return WidpayAPI
     */
    private function getApi()
    {
        return new WidepayAPI(
            $this->meta['wallet_id'],
            $this->meta['wallet_token']
        );
    }

    /**
     * Generates a redirect to the specified url.
     *
     * @param string $url The url to be redirected
     * @return bool True if the redirection was successful, false otherwise
     */
    private function redirectToUrl($url)
    {
        try {
            header('Location: ' . $url);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
