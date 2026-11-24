<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;

final class LocationHttpTransport
{
    /** Creates a bounded HTTPS transport for a single provider operation. */
    public static function create(bool $cookies = false): Closure
    {
        $handle = function_exists('curl_init') ? curl_init() : false;
        if ($handle !== false && $cookies) {
            curl_setopt($handle, CURLOPT_COOKIEFILE, '');
        }
        return static function (string $url, array $headers, ?array $json = null) use ($handle): array
        {
            if ($handle === false || !str_starts_with($url, 'https://')) {
                return ['status' => 0, 'body' => ''];
            }
            $body = '';
            $method = $json === null ? [CURLOPT_HTTPGET => true] : [CURLOPT_POSTFIELDS => json_encode($json, JSON_THROW_ON_ERROR)];
            curl_setopt_array($handle, $method + [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'OpenWoIst (Symcon)',
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_ENCODING       => '',
                CURLOPT_WRITEFUNCTION  => static function ($curl, string $chunk) use (&$body): int
                {
                    $length = strlen($chunk);
                    if (strlen($body) + $length > 2 * 1024 * 1024) {
                        return 0;
                    }
                    $body .= $chunk;
                    return $length;
                }
            ]);
            if (curl_exec($handle) === false) {
                return ['status' => 0, 'body' => ''];
            }
            return ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
        };
    }
}
