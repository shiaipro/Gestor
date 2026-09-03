<?php

class AsaasClient
{
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey, $sandbox = false)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $sandbox ? 'https://sandbox.asaas.com/api/v3' : 'https://www.asaas.com/api/v3';
    }

    private function request($method, $endpoint, $data = [])
    {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $this->apiKey,
            'User-Agent: SENPIPE/1.0'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);

        if ($httpCode >= 400) {
            if (json_last_error() === JSON_ERROR_NONE && isset($json['errors'][0]['description'])) {
                $errorMsg = $json['errors'][0]['description'];
            } else {
                // Se não for JSON válido ou não tiver a estrutura padrão de erro, mostra a resposta crua
                $errorMsg = !empty($response) ? $response : "Sem resposta do servidor (HTTP $httpCode)";
            }
            throw new Exception("Asaas Error ($httpCode): $errorMsg");
        }

        return $json;
    }

    // --- Clientes ---

    public function createCustomer($name, $cpf, $email = null)
    {
        // Buscar se já existe pelo CPF
        try {
            $existing = $this->request('GET', '/customers', ['cpfCnpj' => $cpf]);
            if (!empty($existing['data'])) {
                return $existing['data'][0]['id'];
            }
        } catch (Exception $e) {
            // Ignorar erro na busca e tentar criar
        }

        $data = [
            'name' => $name,
            'cpfCnpj' => $cpf
        ];
        if ($email)
            $data['email'] = $email;

        $response = $this->request('POST', '/customers', $data);
        return $response['id'];
    }

    // --- Cobranças ---

    public function createPayment($customerId, $value, $dueDate, $description = null, $billingType = 'BOLETO')
    {
        $data = [
            'customer' => $customerId,
            'billingType' => $billingType, // BOLETO, PIX, CREDIT_CARD
            'value' => $value,
            'dueDate' => $dueDate,
            'description' => $description
        ];

        return $this->request('POST', '/payments', $data);
    }

    public function getPayment($id)
    {
        return $this->request('GET', "/payments/$id");
    }

    // Retorna o QR Code (imagem base64) e o "copia e cola" de uma cobrança PIX já criada
    public function getPixQrCode($id)
    {
        return $this->request('GET', "/payments/$id/pixQrCode");
    }

    public function deletePayment($id)
    {
        return $this->request('DELETE', "/payments/$id");
    }

    // --- Assinaturas ---

    public function createSubscription($customerId, $value, $nextDueDate, $description = null, $cycle = 'MONTHLY', $billingType = 'BOLETO')
    {
        $data = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $value,
            'nextDueDate' => $nextDueDate,
            'description' => $description,
            'cycle' => $cycle // MONTHLY, WEEKLY, etc
        ];

        return $this->request('POST', '/subscriptions', $data);
    }

    public function updateSubscription($id, $data)
    {
        return $this->request('POST', "/subscriptions/$id", $data); // Asaas usa POST para update parcial ou PUT? Doc v3 diz POST para update de alguns campos ou PUT
        // Na v3, normalmente é POST para /subscriptions/{id} com os campos a alterar
    }

    public function getSubscription($id)
    {
        return $this->request('GET', "/subscriptions/$id");
    }

    public function deleteSubscription($id)
    {
        return $this->request('DELETE', "/subscriptions/$id");
    }
}
