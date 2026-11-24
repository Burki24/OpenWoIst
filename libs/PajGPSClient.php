<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;

final class PajGPSClient
{
    private const API = 'https://connect.paj-gps.de/api/v1';

    public static function devices(array $account, array $session, Closure $request): array
    {
        $result = self::read('/device', null, $account, $session, $request);
        $devices = [];
        foreach ($result['rows'] as $row) {
            $id = $row['id'] ?? null;
            if (is_int($id) && $id > 0) {
                $devices[$id] = is_string($row['name'] ?? null) ? trim($row['name']) : '';
            }
        }
        return ['status' => $result['status'], 'session' => $result['session'], 'devices' => $devices];
    }

    public static function positions(array $ids, int $now, array $account, array $session, Closure $request): array
    {
        $result = self::read('/trackerdata/getalllastpositions', ['deviceIDs' => $ids], $account, $session, $request);
        $fixes = [];
        foreach ($result['rows'] as $row) {
            $id = $row['iddevice'] ?? null;
            if (!in_array($id, $ids, true)) {
                continue;
            }
            $fix = LocationSnapshot::fix([
                'latitude'  => $row['lat'] ?? null,
                'longitude' => $row['lng'] ?? null,
                'timestamp' => $row['dateunix'] ?? null
            ], $now);
            if ($fix !== null && $fix['timestamp'] > ($fixes[$id]['timestamp'] ?? 0)) {
                $fixes[$id] = $fix;
            }
        }
        return ['status' => $result['status'], 'session' => $result['session'], 'fixes' => $fixes];
    }

    private static function read(string $path, ?array $json, array $account, array $session, Closure $request): array
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $fetch = static fn (array $session): array => $request(self::API . $path, [...$headers, 'Authorization: Bearer ' . $session['token']], $json);
        $response = $session === [] ? ['status' => 401] : $fetch($session);
        if ($response['status'] === 401) {
            $auth = self::authenticate($account, $session, $headers, $request);
            $session = $auth['session'];
            $response = $session === [] ? ['status' => $auth['status']] : $fetch($session);
            if ($response['status'] === 401) {
                $session = [];
            }
        }
        $status = $response['status'];
        $rows = $status === 200 ? (json_decode($response['body'], true, 16)['success'] ?? null) : [];
        if (!is_array($rows) || !array_is_list($rows)) {
            $status = 0;
            $rows = [];
        }
        return ['status' => $status, 'session' => $session, 'rows' => $rows];
    }

    private static function authenticate(array $account, array $session, array $headers, Closure $request): array
    {
        if (isset($session['refresh_token'])) {
            $response = $request(self::API . '/updatetoken', $headers, ['email' => $account['email'], 'refresh_token' => $session['refresh_token']]);
            if (!in_array($response['status'], [400, 401, 403, 404], true)) {
                return self::authenticationResult($response);
            }
        }
        return self::authenticationResult($request(self::API . '/login', $headers, $account));
    }

    private static function authenticationResult(array $response): array
    {
        $status = $response['status'];
        $data = $status === 200 ? (json_decode($response['body'], true, 16)['success'] ?? null) : null;
        $token = $data['token'] ?? null;
        $refresh = $data['refresh_token'] ?? null;
        if (!is_string($token) || preg_match('/\A[A-Za-z0-9._~+\/\-]+=*\z/', $token) !== 1) {
            return ['status' => $status === 200 ? 0 : $status, 'session' => []];
        }
        $session = ['token' => $token];
        if (is_string($refresh) && $refresh !== '') {
            $session['refresh_token'] = $refresh;
        }
        return ['status' => 200, 'session' => $session];
    }
}
