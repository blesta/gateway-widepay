<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'widepay_response.php';

/**
 * Wide Pay API
 *
 * @package blesta
 * @subpackage blesta.components.gateways.widepay.apis
 * @copyright Copyright (c) 2019, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class WidepayApi
{
    /**
     * @var string The API URL
     */
    private $apiUrl = 'https://api.widepay.com/v1';
    /**
     * @var string The Widepay wallet ID
     */
    private $walletId;
    /**
     * @var string The Widepay wallet token
     */
    private $walletToken;
    /**
     * @var string The currency to use
     */
    private $currency;

    /**
     * Initializes the request parameter
     *
     * @param string $walletId The wallet ID
     * @param string $walletToken The wallet token
     */
    public function __construct($walletId, $walletToken)
    {
        $this->walletId = $walletId;
        $this->walletToken = $walletToken;
    }

    /**
     * Send an API request to WidePay
     *
     * @param string $route The path to the API method
     * @param array $body The data to be sent
     * @param string $method Data transfer method (POST, GET, PUT, DELETE)
     * @return array
     */
    private function apiRequest($route, array $body, $method)
    {
        $url = $this->apiUrl . '/' . $route;
        $curl = curl_init();

        switch (strtoupper($method)) {
            case 'GET':
            case 'DELETE':
                $url .= empty($body) ? '' : '?' . http_build_query($body);
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, 1);
            default:
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
                break;
        }

        var_dump($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERPWD, $this->walletId . ':' . $this->walletToken);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSLVERSION, 0);

        $headers = [];
        $headers[] = 'WP-API: SDK-PHP';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $this->lastRequest = ['content' => $body, 'headers' => $headers];
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            $error = [
                'error' => 'Curl Error',
                'message' => 'An internal error occurred, or the server did not respond to the request.',
                'status' => 500
            ];

            return new WidepayResponse(['content' => json_encode($error)]);
        }
        curl_close($curl);

        print_r($result);
        $authorization = '';
        $data = explode("\n", $result);
        foreach ($data as $part) {
            $splitPart = explode(':', $part);
            if ($splitPart[0] == 'Authorization' && isset($splitPart[1])) {
                $authorization = $splitPart[1];
                break;
            }
        }

        // Return request response
        return new WidepayResponse(['content' => $data[count($data) - 1], 'headers' => $authorization]);
    }

    /**
     *
     */
    public function createCharge($params)
    {
        $params = [
            'forma' => 'Cartão', // Card
            'cliente' => 'First Test',
            'pessoa' => 'Física',
            'cpf' => '463.384.662-02', // Natural Persons Register (Cadastro de Pessoas Físicas), required for Física charges
            'email' => 'firsttest@mailinator.com', // Optional
            'telefone' => '67 98888-0000', // Telephone Number, optional
            'endereco' => [ // Address, optional
                'rua' => 'Rua Primeiro de Julho', // Street
                'numero' => '192', // Number
                'complemento' => 'Sala 25', // Full Address
                'bairro' => 'Vila Carvalho', // Neighborhood
                'cep' => '79005-610', // Zip Code
                'cidade' => 'Campo Grande', // City
                'estado' => 'MS', // State
                'coletar' => 'Sim' // Option for the payment screen to prompt the customer to enter a delivery address, either Não(no) or Sim(yes)
            ],
            'items' => [ // Items
                [
                    'descricao' => 'Descrição item 1', // Description
                    'valor' => 20, // Value
                ]
            ],
            'referencia' => 'Fatura 12345', // Reference, optional, Reference code to associate a specific ID of your system or application with billing, maximum length: 100 characters
            'notificacao' => 'https://www.blesta.us/452/callback/gw/1/widepay/', // Notification, optional, URL to be called when billing changes status
        ];

        return $this->apiRequest('recebimentos/cobrancas/adicionar', $params, 'POST');
    }

    /**
     *
     */
    public function setupCard($params)
    {
//        $params = [
//            'cliente' => 'First Test', // Client name
//            'pessoa' => 'Física', // Type of issuer
//            'cpf' => '463.384.662-02', // Natural Persons Register (Cadastro de Pessoas Físicas), required for Física charges
//            'email' => 'firsttest@mailinator.com', // Optional
//            'telefone' => '67 98888-0000', // Telephone Number, optional
//            'endereco' => [ // Address, optional
//                'rua' => 'Rua Primeiro de Julho', // Street name
//                'numero' => '192', // Street number
//                'complemento' => 'Sala 25', // Suite #
//                'bairro' => 'Vila Carvalho', // Neighborhood/district
//                'cep' => '79005-610', // Zip Code
//                'cidade' => 'Campo Grande', // City
//                'estado' => 'MS', // State
//                'coletar' => 'Sim' // Option for the payment screen to prompt the customer to enter a delivery address, either Não(no) or Sim(yes)
//            ],
//            'itens' => [ // Items
//                [
//                    'descricao' => 'Descrição item 1', // Description
//                    'valor' => 20, // Value
//                ]
//            ],
//            'notificacao' => 'https://www.blesta.us/452/callback/gw/1/widepay/', // Notification, optional, URL to be called when billing changes status
//            'vencimento' => '2019-05-10',
//            'parcelas' => 2,
//            'dividir' => 'Não'
//        ];
//
//        return $this->apiRequest('recebimentos/carnes/adicionar', $params, 'POST');

//        $params = [
//            'forma' => 'Cartão', // Card
//            'cliente' => 'First Test',
//            'pessoa' => 'Física',
//            'cpf' => '463.384.662-02', // Natural Persons Register (Cadastro de Pessoas Físicas), required for Física charges
//            'email' => 'firsttest@mailinator.com', // Optional
//            'telefone' => '67 98888-0000', // Telephone Number, optional
//            'endereco' => [ // Address, optional
//                'rua' => 'Rua Primeiro de Julho', // Street
//                'numero' => '192', // Number
//                'complemento' => 'Sala 25', // Full Address
//                'bairro' => 'Vila Carvalho', // Neighborhood
//                'cep' => '79005-610', // Zip Code
//                'cidade' => 'Campo Grande', // City
//                'estado' => 'MS', // State
//                'coletar' => 'Sim' // Option for the payment screen to prompt the customer to enter a delivery address, either Não(no) or Sim(yes)
//            ],
//            'itens' => [ // Items
//                [
//                    'descricao' => 'Descrição item 1', // Description
//                    'valor' => 20, // Value
//                ]
//            ],
//            'referencia' => 'Fatura 12345', // Reference, optional, Reference code to associate a specific ID of your system or application with billing, maximum length: 100 characters
//            'notificacao' => 'https://www.blesta.us/452/callback/gw/1/widepay/', // Notification, optional, URL to be called when billing changes status
//        ];
//
//        return $this->apiRequest('recebimentos/cobrancas/adicionar', $params, 'POST');
//
        $params = [
            'forma' => 'Boleto', // Ticket
            'cliente' => 'First Test',
            'pessoa' => 'Física',
            'cpf' => '463.384.662-02', // Natural Persons Register (Cadastro de Pessoas Físicas), required for Física charges
            'email' => 'firsttest@mailinator.com', // Optional
            'telefone' => '67 98888-0000', // Telephone Number, optional
            'endereco' => [ // Address, optional
                'rua' => 'Rua Primeiro de Julho', // Street
                'numero' => '192', // Number
                'complemento' => 'Sala 25', // Full Address
                'bairro' => 'Vila Carvalho', // Neighborhood
                'cep' => '79005-610', // Zip Code
                'cidade' => 'Campo Grande', // City
                'estado' => 'MS', // State
                'coletar' => 'Sim' // Option for the payment screen to prompt the customer to enter a delivery address, either Não(no) or Sim(yes)
            ],
            'itens' => [ // Items
                [
                    'descricao' => 'Descrição item 1', // Description
                    'valor' => 20, // Value
                ]
            ],
            'referencia' => 'Fatura 12345', // Reference, optional, Reference code to associate a specific ID of your system or application with billing, maximum length: 100 characters
            'notificacao' => 'https://www.blesta.us/452/callback/gw/1/widepay/', // Notification, optional, URL to be called when billing changes status
            'vencimento' => '2019-05-10',
        ];

        return $this->apiRequest('recebimentos/cobrancas/adicionar', $params, 'POST');

//        $params = [
//            'cobrancas' => [
//                '422F78B5F7B756F0',
//                '1D1A7E1557183CCC'
//            ]
//        ];

//        return $this->apiRequest('recebimentos/carnes/montar', $params, 'POST');
    }

    /**
     * Sets the currency code to be used for all subsequent requests
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent requests
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Returns $value if $value isset, otherwise returns $alt
     *
     * @param mixed $value The value to return if $value isset
     * @param mixed $alt The value to return if $value is not set
     * @return mixed Either $value or $alt
     */
    protected function ifSet(&$value, $alt = null)
    {
        if (isset($value)) {
            return $value;
        }
        return $alt;
    }
}
