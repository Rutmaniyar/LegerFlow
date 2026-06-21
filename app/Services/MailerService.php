<?php

declare(strict_types=1);

namespace App\Services;

final class MailerService
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function send(string $to, string $subject, string $body, array $attachments = []): bool
    {
        $this->lastError = null;

        $to = filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
        if ($to === '') {
            $this->lastError = 'The recipient address is not a valid email address.';
            return false;
        }

        $subject = $this->headerText($subject);
        $mail = $this->settings();
        $headers = [
            'From: ' . $this->mailbox($mail['from_email'] ?? '', $mail['from_name'] ?? ''),
            'MIME-Version: 1.0',
        ];

        if ($attachments === []) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            if (($mail['transport'] ?? 'mail') === 'smtp' && ($mail['host'] ?? '') !== '') {
                return $this->smtp($mail, $to, $subject, $body, $headers);
            }
            return $this->phpMail($to, $subject, $body, $headers);
        }

        $boundary = 'lf_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $message = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$body}\r\n";

        foreach ($attachments as $attachment) {
            $message .= "--{$boundary}\r\n";
            $message .= 'Content-Type: ' . ($attachment['mime'] ?? 'application/octet-stream') . '; name="' . addslashes($attachment['name']) . '"' . "\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . addslashes($attachment['name']) . '"' . "\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
        }

        $message .= "--{$boundary}--";
        if (($mail['transport'] ?? 'mail') === 'smtp' && ($mail['host'] ?? '') !== '') {
            return $this->smtp($mail, $to, $subject, $message, $headers);
        }

        return $this->phpMail($to, $subject, $message, $headers);
    }

    public function template(string $key, array $vars): array
    {
        $row = app()->db()->fetch('SELECT subject, body FROM email_templates WHERE template_key = ?', [$key]);
        $subject = $row['subject'] ?? '';
        $body = $row['body'] ?? '';

        foreach ($vars as $name => $value) {
            $subject = str_replace('{{' . $name . '}}', (string) $value, $subject);
            $body = str_replace('{{' . $name . '}}', (string) $value, $body);
        }

        return [$subject, $body];
    }

    private function mailbox(string $email, string $name): string
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'no-reply@example.com';
        $name = $this->headerText($name);

        return $name !== '' ? sprintf('"%s" <%s>', addslashes($name), $email) : $email;
    }

    private function headerText(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value));
    }

    private function settings(): array
    {
        if (!app()->isInstalled()) {
            return config('mail', []);
        }

        $settings = new SettingsService();
        return [
            'transport' => $settings->get('mail_transport', config('mail.transport', 'mail')),
            'host' => $settings->get('mail_host', config('mail.host', '')),
            'port' => (int) $settings->get('mail_port', (string) config('mail.port', 587)),
            'username' => $settings->get('mail_username', config('mail.username', '')),
            'password' => $settings->get('mail_password', config('mail.password', '')),
            'encryption' => $settings->get('mail_encryption', config('mail.encryption', 'tls')),
            'from_email' => $settings->get('mail_from_email', config('mail.from_email', '')),
            'from_name' => $settings->get('mail_from_name', config('mail.from_name', '')),
        ];
    }

    private function phpMail(string $to, string $subject, string $body, array $headers): bool
    {
        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$result) {
            $this->lastError = error_get_last()['message'] ?? 'PHP mail() returned false. No local mail transport is configured.';
        }

        return $result;
    }

    private function smtp(array $mail, string $to, string $subject, string $body, array $headers): bool
    {
        $host = (string) $mail['host'];
        $port = (int) ($mail['port'] ?? 587);
        $scheme = ($mail['encryption'] ?? '') === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $error, 15);
        if (!$socket) {
            $this->lastError = "Could not connect to {$host}:{$port} ({$error}).";
            return false;
        }

        $read = static function () use ($socket): string {
            $response = '';
            while (($line = fgets($socket, 515)) !== false) {
                $response .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $response;
        };
        $write = static fn (string $line) => fwrite($socket, $line . "\r\n");
        $expect = function (string $response, string $step) {
            $code = (int) substr($response, 0, 3);
            if ($code < 200 || $code >= 400) {
                $this->lastError = "{$step} failed: " . trim($response !== '' ? $response : 'no response from server');
                return false;
            }
            return true;
        };

        if (!$expect($read(), 'Server greeting')) {
            fclose($socket);
            return false;
        }

        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (!$expect($read(), 'EHLO')) {
            fclose($socket);
            return false;
        }

        if (($mail['encryption'] ?? '') === 'tls') {
            $write('STARTTLS');
            if (!$expect($read(), 'STARTTLS')) {
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'STARTTLS negotiation failed while upgrading to an encrypted connection.';
                fclose($socket);
                return false;
            }
            $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if (!$expect($read(), 'EHLO after STARTTLS')) {
                fclose($socket);
                return false;
            }
        }

        if (($mail['username'] ?? '') !== '') {
            $write('AUTH LOGIN');
            if (!$expect($read(), 'AUTH LOGIN')) {
                fclose($socket);
                return false;
            }
            $write(base64_encode((string) $mail['username']));
            if (!$expect($read(), 'SMTP username')) {
                fclose($socket);
                return false;
            }
            $write(base64_encode((string) ($mail['password'] ?? '')));
            if (!$expect($read(), 'SMTP authentication')) {
                fclose($socket);
                return false;
            }
        }

        $from = filter_var((string) ($mail['from_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: 'no-reply@example.com';
        $write('MAIL FROM:<' . $from . '>');
        if (!$expect($read(), 'MAIL FROM')) {
            fclose($socket);
            return false;
        }
        $write('RCPT TO:<' . $to . '>');
        if (!$expect($read(), 'RCPT TO')) {
            fclose($socket);
            return false;
        }
        $write('DATA');
        if (!$expect($read(), 'DATA')) {
            fclose($socket);
            return false;
        }
        $write('To: <' . $to . '>');
        $write('Subject: ' . $subject);
        foreach ($headers as $header) {
            $write($header);
        }
        $write('');
        fwrite($socket, str_replace("\n.", "\n..", $body) . "\r\n.\r\n");
        if (!$expect($read(), 'Message delivery')) {
            fclose($socket);
            return false;
        }
        $write('QUIT');
        fclose($socket);

        return true;
    }
}
