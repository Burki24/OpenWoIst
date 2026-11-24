<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;

final class GarminLiveTrackClient
{
    public const SHARE_LINK_PATTERN = '^https://livetrack\.garmin\.com/session/([A-Za-z0-9_-]{1,128})(?:/token/|\?token=)([A-Za-z0-9_-][A-Za-z0-9._~-]{0,511})$';
    private const ORIGIN = 'https://livetrack.garmin.com';

    public static function session(string $link): ?array
    {
        if (preg_match('#' . self::SHARE_LINK_PATTERN . '#D', trim($link), $parts) !== 1) {
            return null;
        }
        return ['id' => $parts[1], 'token' => $parts[2], 'url' => self::ORIGIN . '/session/' . $parts[1] . '/token/' . $parts[2]];
    }

    /** Uses the endpoints used by the shared Garmin Connect LiveTrack web page. */
    public static function poll(array $session, ?array $cursor, int $now, Closure $request): array
    {
        $headers = ['Accept: text/html'];
        $response = $request($session['url'], $headers);
        if ($response['status'] !== 200) {
            return ['status' => $response['status']];
        }

        $headers = ['Accept: application/json', 'Referer: ' . $session['url']];
        if (preg_match('/<meta\b(?=[^>]*\b(?:name|property)=["\'](?:livetrack-)?csrf-token["\'])[^>]*\bcontent=["\']([A-Za-z0-9._~+=\/-]{1,1024})["\']/i', $response['body'], $match) === 1) {
            $headers[] = 'Livetrack-Csrf-Token: ' . $match[1];
        }
        $query = ['token' => $session['token']];
        $response = $request(self::ORIGIN . '/api/v2/sessions/' . $session['id'] . '?' . http_build_query($query), $headers);
        if ($response['status'] !== 200) {
            return ['status' => $response['status']];
        }
        $metadata = self::sessionMetadata(json_decode($response['body'], true, 24), $session['id']);
        if ($metadata === null) {
            return ['status' => 0];
        }
        $end = self::time($metadata['end'] ?? null);
        $ended = $end !== null && $end[0] <= $now;

        if ($cursor !== null) {
            $query['begin'] = gmdate('Y-m-d\TH:i:s', $cursor[0]) . '.' . substr($cursor[1], 0, 6) . 'Z';
        }
        $response = $request(self::ORIGIN . '/api/sessions/' . $session['id'] . '/track-points/common?' . http_build_query($query), $headers);
        if ($response['status'] !== 200) {
            return ['status' => $response['status'], 'ended' => $ended];
        }
        $points = self::trackPoints(json_decode($response['body'], true, 24));
        if ($points === null) {
            return ['status' => 0, 'ended' => $ended];
        }

        $fix = null;
        foreach ($points as $point) {
            if (!is_array($point)) {
                continue;
            }
            $events = $point['eventTypes'] ?? $point['event_types'] ?? [];
            $ended = $ended || (is_array($events) && in_array('END', $events, true));
            $time = self::time($point['dateTime'] ?? $point['timestamp'] ?? $point['time'] ?? null);
            if ($time === null || ($cursor !== null && $time <= $cursor)) {
                continue;
            }

            $candidate = self::pointFix($point, $time[0], $now);
            if ($candidate !== null) {
                $fix = $candidate;
                $cursor = $time;
            }
        }
        return ['status' => 200, 'ended' => $ended, 'cursor' => $cursor, 'fix' => $fix];
    }

    /** Reuses cookies across requests for one session poll. */
    public static function transport(): Closure
    {
        $handle = function_exists('curl_init') ? curl_init() : false;
        return static function (string $url, array $headers) use ($handle): array
        {
            if ($handle === false) {
                return ['status' => 0, 'body' => ''];
            }

            $body = '';
            curl_setopt_array($handle, [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'OpenWoIst (Symcon)',
                CURLOPT_COOKIEFILE     => '',
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

    private static function pointFix(array $point, int $timestamp, int $now): ?array
    {
        foreach ([$point['position'] ?? null, $point['fitnessPointData'] ?? null, $point] as $position) {
            if (!is_array($position)) {
                continue;
            }
            $fix = LocationSnapshot::fix([
                'latitude'  => $position['lat'] ?? $position['latitude'] ?? null,
                'longitude' => $position['lon'] ?? $position['lng'] ?? $position['longitude'] ?? null,
                'timestamp' => $timestamp
            ], $now);
            if ($fix !== null) {
                return $fix;
            }
        }
        return null;
    }

    private static function time(mixed $value): ?array
    {
        $format = is_int($value) ? ($value >= 100000000000 ? 'unix-milliseconds' : 'unix-seconds') : 'iso8601';
        return PositionConverter::measurementTime($value, $format);
    }

    private static function sessionMetadata(mixed $data, string $id): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        if (isset($data['sessionId'])) {
            return (is_int($data['sessionId']) || is_string($data['sessionId'])) && (string) $data['sessionId'] === $id ? $data : null;
        }
        if (isset($data['start'])) {
            return $data;
        }
        return self::sessionMetadata($data['session'] ?? $data['data'] ?? null, $id);
    }

    private static function trackPoints(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }
        if (array_is_list($data)) {
            return $data;
        }
        return self::trackPoints($data['trackPoints'] ?? $data['trackpoints'] ?? $data['points'] ?? $data['data'] ?? null);
    }
}
