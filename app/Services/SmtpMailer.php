<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\App;

/**
 * Minimaler SMTP-Client ohne externe Abhängigkeiten.
 * Unterstützt SMTPS (ssl), STARTTLS (tls) und unverschlüsselt (none),
 * AUTH LOGIN/PLAIN sowie multipart/mixed mit PDF-Anhängen.
 *
 * Im Test-Modus (smtp_test_mode) wird nicht versendet, sondern die fertige
 * Nachricht als .eml nach storage/logs/mail geschrieben.
 */
final class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption; // none | tls | ssl
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;
    private bool $testMode;

    /** @var resource|null */
    private $socket = null;

    public function __construct(array $config)
    {
        $this->host = trim((string)($config['host'] ?? ''));
        $this->port = (int)($config['port'] ?? 587);
        $this->encryption = in_array(($config['encryption'] ?? 'tls'), ['none', 'tls', 'ssl'], true) ? (string)$config['encryption'] : 'tls';
        $this->user = (string)($config['user'] ?? '');
        $this->pass = (string)($config['pass'] ?? '');
        $this->fromEmail = trim((string)($config['from_email'] ?? ''));
        $this->fromName = trim((string)($config['from_name'] ?? ''));
        $this->testMode = !empty($config['test_mode']);
    }

    /**
     * @param array $attachments Liste von ['filename' => string, 'content' => binary, 'mime' => string]
     * @param string|null $htmlBody optionale HTML-Variante (multipart/alternative mit $textBody als Fallback)
     */
    public function send(string $to, string $subject, string $textBody, array $attachments = [], ?string $htmlBody = null): void
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Empfänger-Adresse: ' . $to);
        }
        if ($this->fromEmail === '' || !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Absender-Adresse. Bitte SMTP-Einstellungen prüfen.');
        }

        $message = $this->buildMessage($to, $subject, $textBody, $attachments, $htmlBody);

        if ($this->testMode) {
            $this->writeEml($to, $message);
            return;
        }

        if ($this->host === '') {
            throw new \RuntimeException('SMTP-Host ist nicht konfiguriert.');
        }

        $this->transmit($to, $message);
    }

    // ------------------------------------------------------------------
    // MIME
    // ------------------------------------------------------------------

    private function buildMessage(string $to, string $subject, string $textBody, array $attachments, ?string $htmlBody = null): string
    {
        $eol = "\r\n";
        $boundary = 'np_' . bin2hex(random_bytes(16));

        $fromHeader = $this->fromName !== ''
            ? $this->encodeHeader($this->fromName) . ' <' . $this->fromEmail . '>'
            : $this->fromEmail;

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $fromHeader;
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . ($this->host !== '' ? $this->host : 'localhost') . '>';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $body = 'This is a multi-part message in MIME format.' . $eol . $eol;

        if ($htmlBody !== null && trim($htmlBody) !== '') {
            // multipart/alternative: text/plain als Fallback + text/html
            $altBoundary = 'alt_' . bin2hex(random_bytes(16));
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . $eol . $eol;

            $body .= '--' . $altBoundary . $eol;
            $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
            $body .= chunk_split(base64_encode($textBody), 76, $eol);

            $body .= '--' . $altBoundary . $eol;
            $body .= 'Content-Type: text/html; charset=UTF-8' . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
            $body .= chunk_split(base64_encode($htmlBody), 76, $eol);

            $body .= '--' . $altBoundary . '--' . $eol;
        } else {
            // Text-Teil
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Type: text/plain; charset=UTF-8' . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol . $eol;
            $body .= chunk_split(base64_encode($textBody), 76, $eol);
        }

        foreach ($attachments as $att) {
            if (!is_array($att)) {
                continue;
            }
            $filename = (string)($att['filename'] ?? 'anhang.pdf');
            $content = (string)($att['content'] ?? '');
            $mime = (string)($att['mime'] ?? 'application/pdf');
            if ($content === '') {
                continue;
            }

            $safeName = str_replace(['"', "\r", "\n"], '', $filename);
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Type: ' . $mime . '; name="' . $safeName . '"' . $eol;
            $body .= 'Content-Transfer-Encoding: base64' . $eol;
            $body .= 'Content-Disposition: attachment; filename="' . $safeName . '"' . $eol . $eol;
            $body .= chunk_split(base64_encode($content), 76, $eol);
        }

        $body .= '--' . $boundary . '--' . $eol;

        return implode($eol, $headers) . $eol . $eol . $body;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[\x80-\xFF]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function writeEml(string $to, string $message): void
    {
        $dir = App::basePath('storage/logs/mail');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $safeTo = preg_replace('/[^a-zA-Z0-9_.@-]/', '_', $to);
        $file = $dir . '/' . date('Ymd_His') . '_' . $safeTo . '.eml';
        if (@file_put_contents($file, $message) === false) {
            throw new \RuntimeException('Test-Modus: .eml konnte nicht geschrieben werden: ' . $file);
        }
    }

    // ------------------------------------------------------------------
    // SMTP
    // ------------------------------------------------------------------

    private function transmit(string $to, string $message): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr . ' (' . $errno . ')');
        }
        $this->socket = $socket;
        stream_set_timeout($socket, 30);

        try {
            $this->expect([220]);

            $ehloHost = gethostname() ?: 'localhost';
            $this->command('EHLO ' . $ehloHost, [250]);

            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                $ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new \RuntimeException('STARTTLS-Handshake fehlgeschlagen.');
                }
                $this->command('EHLO ' . $ehloHost, [250]);
            }

            if ($this->user !== '') {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->user), [334]);
                $this->command(base64_encode($this->pass), [235]);
            }

            $this->command('MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);

            // Dot-Stuffing: Zeilen, die mit "." beginnen, verdoppeln
            $data = preg_replace('/^\./m', '..', $message);
            $this->write($data . "\r\n.\r\n");
            $this->expect([250]);

            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function command(string $line, array $expectedCodes): string
    {
        $this->write($line . "\r\n");
        return $this->expect($expectedCodes);
    }

    private function write(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('SMTP-Verbindung nicht offen.');
        }
        $written = @fwrite($this->socket, $data);
        if ($written === false) {
            throw new \RuntimeException('SMTP-Schreibfehler.');
        }
    }

    private function expect(array $expectedCodes): string
    {
        $response = '';
        while (true) {
            $line = fgets($this->socket, 2048);
            if ($line === false) {
                throw new \RuntimeException('SMTP-Server hat die Verbindung beendet oder Timeout.');
            }
            $response .= $line;
            // Multiline-Antworten: "250-..." bis "250 ..."
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('SMTP-Fehler: ' . trim($response));
        }
        return $response;
    }
}
