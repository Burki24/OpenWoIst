<?php

declare(strict_types=1);

namespace OpenWoIst;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMNode;
use DOMXPath;

final class GarminMapShareClient
{
    public const LINK_PATTERN = '^https://(?:share\.garmin\.com/(?:[Ff]eed/[Ss]hare/)?|(?:inreach|explore)\.garmin\.com/[Ff]eed/[Ss]hare/)([A-Za-z0-9_-]{1,128})/?$';
    private const KML_NAMESPACE = 'http://www.opengis.net/kml/2.2';

    public static function feed(mixed $link, mixed $imei): ?array
    {
        if (!is_string($link) || !is_string($imei) || preg_match('#' . self::LINK_PATTERN . '#D', trim($link), $parts) !== 1
            || preg_match('/^[0-9]{15}$/D', trim($imei)) !== 1) {
            return null;
        }
        $imei = trim($imei);
        // The documented inreach.garmin.com route redirects to Garmin Explore.
        return ['url' => 'https://explore.garmin.com/feed/share/' . $parts[1] . '?imei=' . $imei, 'imei' => $imei];
    }

    public static function poll(array $feed, ?array $cursor, int $now, Closure $request): array
    {
        $headers = ['Accept: application/vnd.google-earth.kml+xml, application/xml'];
        if ($feed['username'] !== '' || $feed['password'] !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($feed['username'] . ':' . $feed['password']);
        }
        $response = $request($feed['url'], $headers);
        if ($response['status'] !== 200) {
            return ['status' => $response['status']];
        }
        $latest = self::latestPoint($response['body'], $feed['imei'], $now);
        if ($latest === null || ($cursor !== null && $latest['cursor'] < $cursor)) {
            return ['status' => 0];
        }
        return $latest['cursor'] === $cursor ? ['status' => 200] : ['status' => 200] + $latest;
    }

    private static function latestPoint(string $body, string $imei, int $now): ?array
    {
        if ($body === '' || !class_exists(DOMDocument::class)) {
            return null;
        }
        $document = new DOMDocument();
        $options = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | (defined('LIBXML_NO_XXE') ? LIBXML_NO_XXE : 0);
        if (!$document->loadXML($body, $options) || $document->doctype !== null || $document->documentElement?->localName !== 'kml'
            || $document->documentElement->namespaceURI !== self::KML_NAMESPACE) {
            return null;
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('k', self::KML_NAMESPACE);
        $latest = null;
        foreach ($xpath->query('//k:Placemark') as $point) {
            if (self::data($xpath, $point, 'IMEI') !== $imei) {
                continue;
            }
            $time = self::time($xpath, $point);
            if ($time === null || ($latest !== null && $time <= $latest['cursor'])) {
                continue;
            }
            $coordinates = explode(',', trim($xpath->evaluate('string(k:Point/k:coordinates)', $point)));
            $fix = LocationSnapshot::fix([
                'latitude'  => filter_var($coordinates[1] ?? self::data($xpath, $point, 'Latitude'), FILTER_VALIDATE_FLOAT),
                'longitude' => filter_var(isset($coordinates[1]) ? $coordinates[0] : self::data($xpath, $point, 'Longitude'), FILTER_VALIDATE_FLOAT),
                'timestamp' => $time[0]
            ], $now);
            if ($fix !== null) {
                $latest = ['cursor' => $time, 'fix' => $fix];
            }
        }
        return $latest;
    }

    private static function data(DOMXPath $xpath, DOMNode $point, string $name): string
    {
        return trim($xpath->evaluate('string(k:ExtendedData/k:Data[@name="' . $name . '"]/k:value)', $point));
    }

    private static function time(DOMXPath $xpath, DOMNode $point): ?array
    {
        $iso = trim($xpath->evaluate('string(k:TimeStamp/k:when)', $point));
        $time = PositionConverter::measurementTime($iso, 'iso8601');
        if ($time !== null) {
            return $time;
        }
        $utc = self::data($xpath, $point, 'Time UTC');
        $date = DateTimeImmutable::createFromFormat('!n/j/Y g:i:s A', $utc, new DateTimeZone('UTC'));
        return $date !== false && DateTimeImmutable::getLastErrors() === false ? [$date->getTimestamp(), '000000000'] : null;
    }
}
