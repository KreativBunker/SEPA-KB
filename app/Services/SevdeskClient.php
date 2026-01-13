<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SevdeskAccountRepository;

final class SevdeskClient
{
    public function __construct(private SevdeskAccountRepository $repo)
    {
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $acc = $this->repo->getActive();
        if (!$acc) {
            throw new \RuntimeException('sevdesk ist nicht konfiguriert');
        }
        $crypto = new CryptoService();
        $token = $crypto->decrypt($acc['api_token_encrypted']);

        $baseUrl = rtrim($acc['base_url'], '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if (!$ch) {
            throw new \RuntimeException('cURL init fehlgeschlagen');
        }

        $headers = [
            'Accept: application/json',
        ];
        $headerMode = $acc['header_mode'] ?? 'Authorization';
        if ($headerMode === 'X-Authorization') {
            $headers[] = 'X-Authorization: ' . $token;
        } else {
            $headers[] = 'Authorization: ' . $token;
        }

        
        $postFields = null;
        if ($body !== null) {
            // sevdesk API akzeptiert i.d.R. x-www-form-urlencoded (auch für PUT).
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $postFields = http_build_query($body);
        }

curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($postFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }


        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('sevdesk request fehlgeschlagen: ' . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('sevdesk Antwort ist kein JSON, HTTP ' . $code);
        }

        if ($code >= 400) {
            $msg = $data['error']['message'] ?? ($data['message'] ?? 'HTTP ' . $code);
            throw new \RuntimeException('sevdesk Fehler: ' . $msg);
        }

        return $data;
    }

    public function test(): array
    {
        // Contact list is a lightweight test
        return $this->request('GET', '/Contact', ['limit' => 1]);
    }

    public function getInvoices(int $limit = 100, int $offset = 0, ?string $embed = 'contact'): array
    {
        $q = ['limit' => $limit, 'offset' => $offset];
        if ($embed) {
            $q['embed'] = $embed;
        }
        return $this->request('GET', '/Invoice', $q);
    }

    public function getInvoice(int $id, ?string $embed = 'contact'): array
    {
        $q = [];
        if ($embed) {
            $q['embed'] = $embed;
        }
        return $this->request('GET', '/Invoice/' . $id, $q);
    }

    
    public function getContacts(int $limit = 200, int $offset = 0, ?string $embed = null): array
    {
        $q = [
            'limit' => $limit,
            'offset' => $offset,
        ];
        if ($embed) {
            $q['embed'] = $embed;
        }
        return $this->request('GET', '/Contact', $q);
    }

    public function getAllContacts(?string $embed = null, int $pageLimit = 200, int $max = 5000): array
    {
        $all = [];
        $offset = 0;

        while (true) {
            $res = $this->getContacts($pageLimit, $offset, $embed);
            $objs = $res['objects'] ?? [];
            if (!is_array($objs) || empty($objs)) {
                break;
            }

            foreach ($objs as $o) {
                if (is_array($o)) {
                    $all[] = $o;
                }
            }

            if (count($objs) < $pageLimit) {
                break;
            }

            $offset += $pageLimit;
            if ($offset >= $max) {
                break;
            }
        }

        return $all;
    }

public function getContact(int $id, ?string $embed = 'addresses'): array
    {
        $q = [];
        if ($embed) {
            $q['embed'] = $embed;
        }
        return $this->request('GET', '/Contact/' . $id, $q);
    }

public function getPaymentMethods(int $limit = 200, int $offset = 0, ?string $embed = null): array
{
    $q = [
        'limit' => $limit,
        'offset' => $offset,
    ];
    if ($embed) {
        $q['embed'] = $embed;
    }
    return $this->request('GET', '/PaymentMethod', $q);
}

public function getPaymentMethod(int $id, ?string $embed = null): array
{
    $q = [];
    if ($embed) {
        $q['embed'] = $embed;
    }
    return $this->request('GET', '/PaymentMethod/' . $id, $q);
}


    public function updateContactBankData(int $contactId, string $iban, ?string $bic = null): array
    {
        // Kontakt Bankdaten aktualisieren (IBAN/BIC)
        $body = [
            'bankAccount' => $iban,
        ];

        if ($bic !== null && trim($bic) !== '') {
            $body['bankBic'] = $bic;
        }

        try {
            return $this->request('PUT', '/Contact/' . $contactId, [], $body);
        } catch (\Throwable $e) {
            // Fallback für ältere Systeme: BIC Feld kann bankNumber heißen
            if ($bic !== null && trim($bic) !== '') {
                $body2 = [
                    'bankAccount' => $iban,
                    'bankNumber' => $bic,
                ];
                return $this->request('PUT', '/Contact/' . $contactId, [], $body2);
            }
            throw $e;
        }
    }


}
