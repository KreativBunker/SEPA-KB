<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\App;

/**
 * E-Mail-Versand über Microsoft 365 / Exchange Online via Microsoft Graph API.
 *
 * Benötigt eine App-Registrierung in Entra ID (Azure AD) mit der
 * Application-Permission "Mail.Send" (mit Admin-Zustimmung) und sendet
 * per Client-Credentials-Flow als das konfigurierte Absender-Postfach.
 *
 * Im Test-Modus wird nicht versendet, sondern der Graph-Payload als .json
 * nach storage/logs/mail geschrieben.
 */
final class GraphMailer
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $fromEmail;
    private string $fromName;
    private bool $testMode;

    public function __construct(array $config)
    {
        $this->tenantId = trim((string)($config['tenant_id'] ?? ''));
        $this->clientId = trim((string)($config['client_id'] ?? ''));
        $this->clientSecret = (string)($config['client_secret'] ?? '');
        $this->fromEmail = trim((string)($config['from_email'] ?? ''));
        $this->fromName = trim((string)($config['from_name'] ?? ''));
        $this->testMode = !empty($config['test_mode']);
    }

    /**
     * @param array $attachments Liste von ['filename' => string, 'content' => binary, 'mime' => string]
     * @param string|null $htmlBody optionale HTML-Variante (ersetzt den Text-Body in Graph)
     */
    public function send(string $to, string $subject, string $textBody, array $attachments = [], ?string $htmlBody = null): void
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Empfänger-Adresse: ' . $to);
        }
        if ($this->fromEmail === '' || !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Absender-Adresse. Bitte E-Mail-Einstellungen prüfen.');
        }

        $payload = $this->buildPayload($to, $subject, $textBody, $attachments, $htmlBody);

        if ($this->testMode) {
            $this->writeJson($to, $payload);
            return;
        }

        if ($this->tenantId === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new \RuntimeException('Microsoft 365 ist nicht vollständig konfiguriert (Tenant-ID, Client-ID, Client Secret).');
        }

        $token = $this->fetchToken();
        $this->sendMail($token, $payload);
    }

    private function buildPayload(string $to, string $subject, string $textBody, array $attachments, ?string $htmlBody = null): array
    {
        $graphAttachments = [];
        foreach ($attachments as $att) {
            if (!is_array($att)) {
                continue;
            }
            $content = (string)($att['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $graphAttachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => (string)($att['filename'] ?? 'anhang.pdf'),
                'contentType' => (string)($att['mime'] ?? 'application/pdf'),
                'contentBytes' => base64_encode($content),
            ];
        }

        $useHtml = $htmlBody !== null && trim($htmlBody) !== '';
        $message = [
            'subject' => $subject,
            'body' => [
                'contentType' => $useHtml ? 'HTML' : 'Text',
                'content' => $useHtml ? $htmlBody : $textBody,
            ],
            'toRecipients' => [
                ['emailAddress' => ['address' => $to]],
            ],
        ];
        if ($this->fromName !== '') {
            $message['from'] = ['emailAddress' => ['address' => $this->fromEmail, 'name' => $this->fromName]];
        }
        if (!empty($graphAttachments)) {
            $message['attachments'] = $graphAttachments;
        }

        return [
            'message' => $message,
            'saveToSentItems' => true,
        ];
    }

    private function fetchToken(): string
    {
        $url = 'https://login.microsoftonline.com/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token';
        $body = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        $res = $this->httpPost($url, $body, ['Content-Type: application/x-www-form-urlencoded']);
        $data = json_decode($res['body'], true);

        if ($res['code'] >= 400 || !is_array($data) || empty($data['access_token'])) {
            $msg = is_array($data) ? (string)($data['error_description'] ?? ($data['error'] ?? 'HTTP ' . $res['code'])) : 'HTTP ' . $res['code'];
            throw new \RuntimeException('Microsoft 365 Anmeldung fehlgeschlagen: ' . $msg);
        }

        return (string)$data['access_token'];
    }

    private function sendMail(string $token, array $payload): void
    {
        $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($this->fromEmail) . '/sendMail';
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('Graph-Payload konnte nicht erzeugt werden.');
        }

        $res = $this->httpPost($url, $body, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        // Graph antwortet bei Erfolg mit 202 Accepted und leerem Body
        if ($res['code'] >= 400) {
            $data = json_decode($res['body'], true);
            $msg = is_array($data) ? (string)($data['error']['message'] ?? ('HTTP ' . $res['code'])) : 'HTTP ' . $res['code'];
            throw new \RuntimeException('Microsoft 365 Versand fehlgeschlagen: ' . $msg);
        }
    }

    /**
     * @return array{code:int, body:string}
     */
    private function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        if (!$ch) {
            throw new \RuntimeException('cURL init fehlgeschlagen');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Microsoft 365 Anfrage fehlgeschlagen: ' . $err);
        }

        return ['code' => $code, 'body' => (string)$raw];
    }

    private function writeJson(string $to, array $payload): void
    {
        $dir = App::basePath('storage/logs/mail');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safeTo = preg_replace('/[^a-zA-Z0-9_.@-]/', '_', $to);
        $file = $dir . '/' . date('Ymd_His') . '_' . $safeTo . '_graph.json';
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($file, $json) === false) {
            throw new \RuntimeException('Test-Modus: Payload konnte nicht geschrieben werden: ' . $file);
        }
    }
}
