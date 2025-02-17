<?php

namespace PDPhilip\Elasticsearch\Utils;

/**
 * @internal
 *
 * @see https://github.com/elastic/elasticsearch/blob/6b7751d65f7b32be38020688b8867f406a0f608a/server/src/main/java/org/elasticsearch/common/TimeBasedUUIDGenerator.java
 *
 * - Same 15-byte ID structure (timestamp, sequence, MAC).
 * - Atomic sequence number handling (prevents duplicates).
 * - Base64 URL-safe encoding, same as Strings.BASE_64_NO_PADDING_URL_ENCODER.
 * - Matches Elasticsearch’s ID distribution (compression-friendly byte layout).
 *
 * // Rational for the byte layout via Elasticsearch source code:
 * // We have auto-generated ids, which are usually used for append-only workloads.
 * // So we try to optimize the order of bytes for indexing speed (by having quite
 * // unique bytes close to the beginning of the ids so that sorting is fast) and
 * // compression (by making sure we share common prefixes between enough ids)
 */
class TimeBasedUUIDGenerator
{
    private static int $sequenceNumber;

    private static int $lastTimestamp = 0;

    private static string $macAddress;

    private static bool $initialized = false;

    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$sequenceNumber = random_int(0, 0xFFFFFF);
        self::$macAddress = self::getSecureMungedAddress();
        self::$initialized = true;
    }

    public static function generate(): string
    {
        self::initialize();

        // Increment sequence number (max 3 bytes)
        $sequenceId = ++self::$sequenceNumber & 0xFFFFFF;

        // Get timestamp and prevent it from going backward
        $timestamp = max(self::$lastTimestamp, self::getTimestampMillis());

        if ($sequenceId === 0) {
            $timestamp++;
        }

        self::$lastTimestamp = $timestamp;

        // 15-byte UUID byte structure
        $uuidBytes = '';

        // 1st & 3rd byte of sequence ID (for better compression)
        $uuidBytes .= chr($sequenceId & 0xFF);
        $uuidBytes .= chr(($sequenceId >> 16) & 0xFF);

        // Timestamp bytes (ensuring changes are progressive)
        $uuidBytes .= chr(($timestamp >> 16) & 0xFF);
        $uuidBytes .= chr(($timestamp >> 24) & 0xFF);
        $uuidBytes .= chr(($timestamp >> 32) & 0xFF);
        $uuidBytes .= chr(($timestamp >> 40) & 0xFF);

        // Inject munged MAC address
        $uuidBytes .= self::$macAddress;

        // Remaining timestamp & sequence bytes
        $uuidBytes .= chr(($timestamp >> 8) & 0xFF);
        $uuidBytes .= chr(($sequenceId >> 8) & 0xFF);
        $uuidBytes .= chr($timestamp & 0xFF);

        // Base64 URL-safe encoding (removes padding)
        return rtrim(strtr(base64_encode($uuidBytes), '+/', '-_'), '=');
    }

    private static function getTimestampMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private static function getMacAddress(): ?string
    {
        static $cachedMac;
        if ($cachedMac) {
            return $cachedMac;
        }

        // Try system commands first
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec('getmac');
        } else {
            $output = shell_exec('ifconfig -a || ip link');
        }

        if ($output) {
            if (preg_match('/([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/', $output, $matches)) {
                $mac = str_replace([':', '-'], '', $matches[0]);
                $macAddress = hex2bin($mac);
                if (self::isValidAddress($macAddress)) {
                    $cachedMac = $macAddress;

                    return $cachedMac;
                }
            }
        }

        return null;
    }

    private static function isValidAddress(?string $address): bool
    {
        return $address !== null && strlen($address) === 6 && preg_match('/[^\x00]/', $address);
    }

    private static function constructDummyMulticastAddress(): string
    {
        static $cachedDummy;
        if (! $cachedDummy) {
            $dummy = random_bytes(6);
            $dummy[0] = chr(ord($dummy[0]) | 0x01); // Set multicast bit
            $cachedDummy = $dummy;
        }

        return $cachedDummy;
    }

    private static function getSecureMungedAddress(): string
    {
        static $cachedMungedMac;
        if ($cachedMungedMac) {
            return $cachedMungedMac;
        }

        $macAddress = self::getMacAddress();
        if (! $macAddress) {
            $macAddress = self::constructDummyMulticastAddress();
        }

        // Munging the MAC address (Elasticsearch-like obfuscation)
        $mungedMac = '';
        $randomSeed = random_bytes(6);
        for ($i = 0; $i < 6; $i++) {
            $mungedMac .= chr(ord($randomSeed[$i]) ^ ord($macAddress[$i]));
        }

        $cachedMungedMac = $mungedMac;

        return $cachedMungedMac;
    }
}
