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

    /**
     * GET-Request, der die Antwort roh zurückgibt (für Endpunkte, die
     * Dateien als Binary-Stream statt JSON liefern können).
     *
     * @return array{code:int, body:string}
     */
    private function requestRaw(string $path, array $query = []): array
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

        $headers = [];
        $headerMode = $acc['header_mode'] ?? 'Authorization';
        if ($headerMode === 'X-Authorization') {
            $headers[] = 'X-Authorization: ' . $token;
        } else {
            $headers[] = 'Authorization: ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('sevdesk request fehlgeschlagen: ' . $err);
        }

        return ['code' => $code, 'body' => (string)$raw];
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


    public function getDunningInvoices(int $limit = 200, int $offset = 0): array
    {
        // Mahnungen sind Invoice-Objekte mit invoiceType=MA, origin verweist auf die Ursprungsrechnung
        return $this->request('GET', '/Invoice', [
            'invoiceType' => 'MA',
            'limit' => $limit,
            'offset' => $offset,
            'embed' => 'origin',
        ]);
    }

    public function getDunnings(int $invoiceId): array
    {
        return $this->request('GET', '/Invoice/' . $invoiceId . '/getDunnings');
    }

    public function getInvoiceAndReminderAmount(int $invoiceId): array
    {
        return $this->request('GET', '/Invoice/' . $invoiceId . '/getInvoiceAndReminderAmount');
    }

    /**
     * Liefert das Rechnungs-PDF als ['filename' => string, 'content' => binary].
     * preventSendBy=true verhindert, dass sevdesk den Belegstatus auf "versendet" setzt.
     *
     * sevdesk liefert je nach System entweder direkt den PDF-Binary-Stream
     * oder ein JSON-Objekt mit Base64-Inhalt – beides wird unterstützt.
     */
    public function getInvoicePdf(int $invoiceId): array
    {
        $res = $this->requestRaw('/Invoice/' . $invoiceId . '/getPdf', [
            'download' => 'true',
            'preventSendBy' => 'true',
        ]);

        $body = $res['body'];

        if ($res['code'] >= 400) {
            $data = json_decode($body, true);
            $msg = is_array($data) ? (string)($data['error']['message'] ?? ($data['message'] ?? 'HTTP ' . $res['code'])) : 'HTTP ' . $res['code'];
            throw new \RuntimeException('sevdesk Fehler: ' . $msg);
        }

        // Variante 1: roher PDF-Stream
        if (str_starts_with($body, '%PDF')) {
            return [
                'filename' => 'Rechnung_' . $invoiceId . '.pdf',
                'content' => $body,
            ];
        }

        // Variante 2: JSON mit Base64-Inhalt
        $data = json_decode($body, true);
        if (is_array($data)) {
            $obj = $data['objects'] ?? $data;
            if (is_array($obj) && isset($obj[0])) {
                $obj = $obj[0];
            }
            if (is_array($obj) && !empty($obj['content'])) {
                $content = base64_decode((string)$obj['content'], true);
                if ($content !== false && $content !== '') {
                    return [
                        'filename' => (string)($obj['filename'] ?? ('Rechnung_' . $invoiceId . '.pdf')),
                        'content' => $content,
                    ];
                }
            }
            throw new \RuntimeException('sevdesk PDF-Antwort ist leer (Invoice ' . $invoiceId . ')');
        }

        // Variante 3: Base64-String ohne JSON-Hülle
        $decoded = base64_decode(trim($body), true);
        if ($decoded !== false && str_starts_with($decoded, '%PDF')) {
            return [
                'filename' => 'Rechnung_' . $invoiceId . '.pdf',
                'content' => $decoded,
            ];
        }

        throw new \RuntimeException('sevdesk PDF-Antwort hat ein unbekanntes Format (Invoice ' . $invoiceId . ', HTTP ' . $res['code'] . ')');
    }

    /**
     * Erzeugt in sevdesk eine Mahnung zur Rechnung. sevdesk vergibt die
     * Mahnstufe automatisch (1. Aufruf = Zahlungserinnerung, danach 1./2. Mahnung).
     * Liefert das neue Mahn-Belegobjekt (Invoice mit invoiceType=MA, Entwurfsstatus).
     */
    public function createInvoiceReminder(int $invoiceId): array
    {
        return $this->request('POST', '/Invoice/Factory/createInvoiceReminder', [
            'invoice[id]' => $invoiceId,
            'invoice[objectName]' => 'Invoice',
        ]);
    }

    public function updateInvoice(int $invoiceId, array $fields): array
    {
        return $this->request('PUT', '/Invoice/' . $invoiceId, [], $fields);
    }

    /**
     * Markiert einen Beleg als versendet (Status 200).
     * sendType: VM = per E-Mail, VPDF = als PDF, VPR = gedruckt, VP = per Post.
     */
    public function invoiceSendBy(int $invoiceId, string $sendType = 'VM'): array
    {
        return $this->request('PUT', '/Invoice/' . $invoiceId . '/sendBy', [], [
            'sendType' => $sendType,
            'sendDraft' => 'false',
        ]);
    }

    public function getCommunicationWays(int $contactId, string $type = 'EMAIL', bool $mainOnly = false): array
    {
        $q = [
            'contact[id]' => $contactId,
            'contact[objectName]' => 'Contact',
            'type' => $type,
        ];
        if ($mainOnly) {
            $q['main'] = 1;
        }
        return $this->request('GET', '/CommunicationWay', $q);
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
