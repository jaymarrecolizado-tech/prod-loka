<?php
/**
 * LOKA - SMS Gateway HTTP client (capcom6 / sms-gate.app compatible)
 */

class SmsGateway
{
    private string $baseUrl;
    private string $apiPath;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(
        string $baseUrl,
        string $username,
        string $password,
        string $apiPath = '/message',
        int $timeout = 15
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiPath = '/' . ltrim($apiPath, '/');
        $this->username = $username;
        $this->password = $password;
        $this->timeout = max(5, $timeout);
    }

    public static function fromConfig(): ?self
    {
        $url = smsConfig('sms_gateway_url');
        $user = smsConfig('sms_gateway_username');
        $pass = smsConfig('sms_gateway_password');
        if ($url === '' || $user === '' || $pass === '') {
            return null;
        }

        return new self(
            $url,
            $user,
            $pass,
            smsConfig('sms_api_path', SMS_API_PATH_LOCAL),
            (int) smsConfig('sms_timeout_seconds', '15')
        );
    }

    public function endpoint(): string
    {
        return $this->baseUrl . $this->apiPath;
    }

    /**
     * @return array{ok:bool,message_id:?string,response:?string,error:?string,http_code:int}
     */
    public function send(string $phoneE164, string $message): array
    {
        $payload = json_encode([
            'textMessage' => ['text' => $message],
            'phoneNumbers' => [$phoneE164],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return [
                'ok' => false,
                'message_id' => null,
                'response' => null,
                'error' => 'Failed to encode SMS payload',
                'http_code' => 0,
            ];
        }

        $ch = curl_init($this->endpoint());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'ok' => false,
                'message_id' => null,
                'response' => is_string($body) ? $body : null,
                'error' => $error !== '' ? $error : 'cURL error ' . $errno,
                'http_code' => $code,
            ];
        }

        $decoded = is_string($body) ? json_decode($body, true) : null;
        $messageId = null;
        if (is_array($decoded)) {
            $messageId = $decoded['id'] ?? $decoded['messageId'] ?? null;
            if (is_string($messageId)) {
                $messageId = substr($messageId, 0, 100);
            } else {
                $messageId = null;
            }
        }

        $ok = $code >= 200 && $code < 300;
        return [
            'ok' => $ok,
            'message_id' => $messageId,
            'response' => is_string($body) ? substr($body, 0, 4000) : null,
            'error' => $ok ? null : ('HTTP ' . $code . (is_string($body) ? ': ' . substr($body, 0, 200) : '')),
            'http_code' => $code,
        ];
    }

    /**
     * @return array{ok:bool,error:?string,http_code:int,body:?string}
     */
    public function health(): array
    {
        $url = $this->baseUrl . '/health';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => min(10, $this->timeout),
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => $error ?: 'cURL error', 'http_code' => $code, 'body' => null];
        }

        return [
            'ok' => $code >= 200 && $code < 300,
            'error' => ($code >= 200 && $code < 300) ? null : ('HTTP ' . $code),
            'http_code' => $code,
            'body' => is_string($body) ? substr($body, 0, 500) : null,
        ];
    }
}
