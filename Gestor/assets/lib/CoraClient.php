<?php

/**
 * Cliente para a API do Banco Cora (Integração Direta com mTLS).
 * Docs: https://developers.cora.com.br/docs/instrucoes-iniciais
 *
 * Autenticação: OAuth2 client_credentials autenticado via certificado + chave privada
 * (mTLS) apresentados em toda requisição — não existe client_secret na integração direta.
 */
class CoraClient
{
    private $clientId;
    private $certPath;
    private $keyPath;
    private $baseUrl;
    private $accessToken = null;

    public function __construct($clientId, $certPath, $keyPath, $sandbox = false)
    {
        $this->clientId = $clientId;
        $this->certPath = $certPath;
        $this->keyPath = $keyPath;
        $this->baseUrl = $sandbox ? 'https://matls-clients.api.stage.cora.com.br' : 'https://matls-clients.api.cora.com.br';
    }

    private function curlBase()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }

    private function getAccessToken()
    {
        if ($this->accessToken) return $this->accessToken;

        $ch = $this->curlBase();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Cora Error (conexão/certificado): $curlError");
        }

        $json = json_decode($response, true);
        if ($httpCode >= 400 || empty($json['access_token'])) {
            $msg = $json['message'] ?? $json['error_description'] ?? ($response ?: "Sem resposta do servidor (HTTP $httpCode)");
            throw new Exception("Cora Error ao autenticar ($httpCode): $msg");
        }

        $this->accessToken = $json['access_token'];
        return $this->accessToken;
    }

    private function request($method, $endpoint, $data = null, $idempotencyKey = null)
    {
        $token = $this->getAccessToken();

        $ch = $this->curlBase();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Cora Error (conexão/certificado): $curlError");
        }

        $json = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $json['message'] ?? ($response ?: "Sem resposta do servidor (HTTP $httpCode)");
            throw new Exception("Cora Error ($httpCode): $msg");
        }

        return $json;
    }

    /**
     * Cria uma cobrança (boleto registrado + PIX) na Cora.
     * $customer: ['name', 'document' => ['identity','type'], 'email']
     * $services: [['name', 'amount' => centavos]]
     */
    public function createInvoice($code, $customer, $services, $dueDate)
    {
        $data = [
            'code' => $code,
            'customer' => $customer,
            'services' => $services,
            'payment_terms' => [
                'due_date' => $dueDate,
            ],
            'payment_forms' => ['BANK_SLIP', 'PIX'],
        ];

        return $this->request('POST', '/v2/invoices/', $data, $this->gerarIdempotencyKey($code));
    }

    public function getInvoice($id)
    {
        return $this->request('GET', '/v2/invoices/' . $id);
    }

    public function cancelInvoice($id)
    {
        return $this->request('DELETE', '/v2/invoices/' . $id);
    }

    /**
     * Cadastra um webhook endpoint na Cora para receber notificações de eventos de invoice.
     */
    public function createWebhookEndpoint($url, $resource = 'invoice', $trigger = 'paid')
    {
        $data = [
            'url' => $url,
            'resource' => $resource,
            'trigger' => $trigger,
        ];

        return $this->request('POST', '/endpoints', $data, $this->gerarIdempotencyKey($url . $resource . $trigger));
    }

    public function listWebhookEndpoints()
    {
        return $this->request('GET', '/endpoints');
    }

    // Idempotency-Key precisa ser um UUID estável para a mesma operação lógica.
    private function gerarIdempotencyKey($seed)
    {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            crc32($seed . '-a'),
            crc32($seed . '-b') & 0xffff,
            (crc32($seed . '-c') & 0x0fff) | 0x4000,
            (crc32($seed . '-d') & 0x3fff) | 0x8000,
            crc32($seed . '-e') & 0xffffffff
        );
    }
}
