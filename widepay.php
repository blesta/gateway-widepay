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
class Widepay extends MerchantGateway implements MerchantCc, MerchantCcOffsite
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
        $this->view->setDefaultView('components' . DS . 'gateways' . DS . 'merchant' . DS . 'widepay' . DS);

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
     * Used to determine whether this gateway can be configured for autodebiting accounts
     *
     * @return bool True if the customer must be present (e.g. in the case of credit card
     *  customer must enter security code), false otherwise
     */
    public function requiresCustomerPresent()
    {
        return false; // TODO double check this value
    }

    /**
     * Informs the system of whether or not this gateway is configured for offsite customer
     * information storage for credit card payments
     *
     * @return bool True if the gateway expects the offset methods to be called for credit card payments,
     *  false to process the normal methods instead
     */
    public function requiresCcStorage()
    {
        return true; // TODO determine what this value should be
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
     * Store a credit card off site
     *
     * @param array $card_info An array of card info to store off site including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expiration date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     * @param array $contact An array of contact information for the billing contact this
     *  account is to be set up under including:
     *  - id The ID of the contact
     *  - client_id The ID of the client this contact resides under
     *  - user_id The ID of the user this contact represents
     *  - contact_type The contact type
     *  - contact_type_id The reference ID for this custom contact type
     *  - contact_type_name The name of the contact type
     *  - first_name The first name of the contact
     *  - last_name The last name of the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - email The email address of the contact
     *  - address1 The address of the contact
     *  - address2 The address line 2 of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     *  - date_added The date/time the contact was added
     * @param string $client_reference_id The reference ID for the client on the remote gateway (if one exists)
     * @return mixed False on failure or an array containing:
     *  - client_reference_id The reference ID for this client
     *  - reference_id The reference ID for this payment account
     */
    public function storeCc(array $card_info, array $contact, $client_reference_id = null)
    {
        $api = $this->getApi();
        $response = $api->setupCard([]);
//        var_dump($contact, $response);
        die;
        return [
            'client_reference_id' => $response['profile_id'],
            'reference_id' => $response['payment_profile_id']
        ];
    }

    /**
     * Update a credit card stored off site
     *
     * @param array $card_info An array of card info to store off site including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expiration date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     *  - account_changed True if the account details (bank account or card number, etc.)
     *  have been updated, false otherwise
     * @param array $contact An array of contact information for the billing contact this
     *  account is to be set up under including:
     *  - id The ID of the contact
     *  - client_id The ID of the client this contact resides under
     *  - user_id The ID of the user this contact represents
     *  - contact_type The contact type
     *  - contact_type_id The reference ID for this custom contact type
     *  - contact_type_name The name of the contact type
     *  - first_name The first name of the contact
     *  - last_name The last name of the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - email The email address of the contact
     *  - address1 The address of the contact
     *  - address2 The address line 2 of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     *  - date_added The date/time the contact was added
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @return mixed False on failure or an array containing:
     *  - client_reference_id The reference ID for this client
     *  - reference_id The reference ID for this payment account
     */
    public function updateCc(array $card_info, array $contact, $client_reference_id, $account_reference_id)
    {
        return [
            'client_reference_id' => $this->ifSet($response['profile_id']),
            'reference_id' => $this->ifSet($response['payment_profile_id'])
        ];
    }

    /**
     * Remove a credit card stored off site
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to remove
     * @return array An array containing:
     *  - client_reference_id The reference ID for this client
     *  - reference_id The reference ID for this payment account
     */
    public function removeCc($client_reference_id, $account_reference_id)
    {
        return [
            'client_reference_id' => $this->ifSet($response['profile_id']),
            'reference_id' => $this->ifSet($response['payment_profile_id'])
        ];
    }

    /**
     * Charge a credit card stored off site
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @param float $amount The amount to process
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function processStoredCc($client_reference_id, $account_reference_id, $amount, array $invoice_amounts = null)
    {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($response['x_last4']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Authorizees a credit card stored off site
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @param float $amount The amount to authorize
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function authorizeStoredCc(
        $client_reference_id,
        $account_reference_id,
        $amount,
        array $invoice_amounts = null
    ) {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($response['x_last4']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Charge a previously authorized credit card stored off site
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @param string $transaction_reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The ID of the previously authorized transaction
     * @param float $amount The amount to capture
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function captureStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id,
        $amount,
        array $invoice_amounts = null
    ) {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($response['x_last4']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Void an off site credit card charge
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @param string $transaction_reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The ID of the previously authorized transaction
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard message for
     *      this transaction status (optional)
     */
    public function voidStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id
    ) {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($response['x_last4']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Refund an off site credit card charge
     *
     * @param string $client_reference_id The reference ID for the client on the remote gateway
     * @param string $account_reference_id The reference ID for the stored account on the remote gateway to update
     * @param string $transaction_reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The ID of the previously authorized transaction
     * @param float $amount The amount to refund
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard message
     *      for this transaction status (optional)
     */
    public function refundStoredCc(
        $client_reference_id,
        $account_reference_id,
        $transaction_reference_id,
        $transaction_id,
        $amount
    ) {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($response['x_last4']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Charge a credit card
     *
     * @param array $card_info An array of credit card info including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expiration date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-character country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     * @param float $amount The amount to charge this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function processCc(array $card_info, $amount, array $invoice_amounts = null)
    {
        $api = $this->getApi();

        $vars = [
            'forma' => 'Cartão', // Card
            'cliente' => $card_info['first_name'] . ' ' . $card_info['last_name'],
            'pessoa' => true ? 'Física'/* Individual Person */ : 'Jurídica'/* Brazillian Company */,
            'cpf' => '', // Natural Persons Register (Cadastro de Pessoas Físicas), required for Física charges
            'cnpj' => '', // National Registry of Legal Entities (Cadastro Nacional da Pessoa Jurídica), required for Jurídica charges
            'email' => '', // Optional
            'telefone' => '', // Telephone Number, optional
            'endereco' => [ // Address, optional
                'rua' => $card_info['address1'], // Street
                'numero' => '', // Number
                'complemento' => $card_info['address2'], // Full Address
                'bairro' => '', // Neighborhood
                'cep' => $card_info['zip'], // Zip Code
                'cidade' => $card_info['city'], // City
                'estado' => $card_info['state']['code'], // State
                'coletar' => 'Sim', // Option for the payment screen to prompt the customer to enter a delivery address, either Não(no) or Sim(yes)
            ],
            'items' => [ // Items
                [
                    'descricao' => '', // Description
                    'valor' => $amount, // Value
                ]
            ],
            'referencia' => '', // Reference, optional, URL to be called when billing changes status
            'notificacao' => '', // Notification, optional, URL that the system will redirect when payment
            // is available and used only if the form attribute is Cartão(card)
            'redirecionamento' => '', // Redirection, optional
            'vencimento' => '', // Maturity, required for Boleto charges
            'enviar' => '', // Submit, optional
            'mensagem' => '', // Message, optional
            'marketplace' => [ // Marketplace, optional
                [
                    'carteira' => '', // Portfolio (wallet)
                    'valor' => '', // Value
                    'item' => '' // Item
                ]
            ],
            'boleto' => [ // Ticket, optional
                'desconto' => '', // Discount
                'multa' => '', // Fine
                'juros' => '', // Interest
                'instrucoes' => '' // Instructions
            ]
        ];

        $api->createCharge($vars);

        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($card_info['card_number']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Authorize a credit card
     *
     * @param array $card_info An array of credit card info including:
     *  - first_name The first name on the card
     *  - last_name The last name on the card
     *  - card_number The card number
     *  - card_exp The card expidation date
     *  - card_security_code The 3 or 4 digit security code of the card (if available)
     *  - address1 The address 1 line of the card holder
     *  - address2 The address 2 line of the card holder
     *  - city The city of the card holder
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the card holder
     * @param float $amount The amount to charge this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function authorizeCc(array $card_info, $amount, array $invoice_amounts = null)
    {
        return [
            'status' => $status,
            'reference_id' => substr($this->ifSet($card_info['card_number']), -4),
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Capture the funds of a previously authorized credit card
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @param float $amount The amount to capture on this card
     * @param array $invoice_amounts An array of invoices, each containing:
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function captureCc($reference_id, $transaction_id, $amount, array $invoice_amounts = null)
    {
        return [
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Void a credit card charge
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function voidCc($reference_id, $transaction_id)
    {
        return [
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
    }

    /**
     * Refund a credit card charge
     *
     * @param string $reference_id The reference ID for the previously authorized transaction
     * @param string $transaction_id The transaction ID for the previously authorized transaction
     * @param float $amount The amount to refund this card
     * @return array An array of transaction data including:
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refundCc($reference_id, $transaction_id, $amount)
    {
        return [
            'status' => $status,
            'reference_id' => null,
            'transaction_id' => $this->ifSet($response['x_trans_id']),
            'message' => $this->ifSet($response['x_response_reason_text'])
        ];
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
}
