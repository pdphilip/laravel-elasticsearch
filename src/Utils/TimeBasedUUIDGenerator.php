<?php

namespace PDPhilip\Elasticsearch\Utils;

use Random\RandomException;

/**
 * @internal
 *
 * @see https://github.com/elastic/elasticsearch/blob/6b7751d65f7b32be38020688b8867f406a0f608a/server/src/main/java/org/elasticsearch/common/TimeBasedUUIDGenerator.java
 *
 * - Same 15-byte ID structure (timestamp, sequence, MAC).
 * - Atomic sequence number handling (prevents duplicates).
 * - Base64 URL-safe encoding, same as Strings.BASE_64_NO_PADDING_URL_ENCODER.
 * - Matches Elasticsearch’s ID distribution (compression-friendly byte layout).
 */
class TimeBasedUUIDGenerator
{
    private static int $sequenceNumber = 0;

    private static int $lastTimestamp = 0;

    private static string $macAddress = '';

    /**
     * @throws RandomException
     */
    public function __construct()
    {
        if (! self::$macAddress) {
            self::$macAddress = self::getMacAddress();
        }
        if (! self::$sequenceNumber) {
            self::$sequenceNumber = random_int(0, 0xFFFFFF); // Random 3-byte sequence start
        }
    }

    /**
     * @throws RandomException
     */
    public function getBase64UUID(): string
    {
        $currentTimestamp = self::currentTimeMillis();

        // Ensure timestamp never goes backward
        if ($currentTimestamp <= self::$lastTimestamp) {
            self::$sequenceNumber = (self::$sequenceNumber + 1) & 0xFFFFFF; // 3-byte counter
            if (self::$sequenceNumber == 0) {
                $currentTimestamp = self::$lastTimestamp + 1;
            }
        } else {
            self::$sequenceNumber = random_int(0, 0xFFFFFF); // Reset sequence when time advances
        }

        self::$lastTimestamp = $currentTimestamp;

        // Generate the 15-byte UUID
        $uuidBytes = [];
        $sequenceId = self::$sequenceNumber;

        // 1st & 3rd byte of sequence ID (for compression)
        $uuidBytes[] = $sequenceId & 0xFF;
        $uuidBytes[] = ($sequenceId >> 16) & 0xFF;

        // 6-byte timestamp
        $uuidBytes[] = ($currentTimestamp >> 16) & 0xFF;
        $uuidBytes[] = ($currentTimestamp >> 24) & 0xFF;
        $uuidBytes[] = ($currentTimestamp >> 32) & 0xFF;
        $uuidBytes[] = ($currentTimestamp >> 40) & 0xFF;

        // 6-byte MAC address (pre-generated)
        foreach (str_split(self::$macAddress) as $byte) {
            $uuidBytes[] = ord($byte);
        }

        // Remaining bytes
        $uuidBytes[] = ($currentTimestamp >> 8) & 0xFF;
        $uuidBytes[] = ($sequenceId >> 8) & 0xFF;
        $uuidBytes[] = $currentTimestamp & 0xFF;

        // Convert to Base64 URL-safe encoding (no padding)
        return rtrim(strtr(base64_encode(pack('C*', ...$uuidBytes)), '+/', '-_'), '=');
    }

    private static function currentTimeMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * @throws RandomException
     */
    private static function getMacAddress(): string
    {
        return random_bytes(6);
    }
}
