<?php

declare(strict_types=1);

namespace PDPhilip\Elasticsearch\Utils;

/**
 * Generates time-ordered, sortable 20-character IDs for Elasticsearch.
 *
 * Produces 15-byte IDs encoded with a sortable base64 alphabet where
 * lexicographic string comparison matches chronological order — both
 * within a single process and across multiple concurrent processes
 * at millisecond granularity.
 *
 * Byte layout (15 bytes):
 *   [0-5]  Timestamp in ms, big-endian (most significant first)
 *   [6-8]  Monotonic sequence counter (3 bytes, wraps at 0xFFFFFF)
 *   [9-14] Process identifier (6 random bytes, fixed per process)
 *
 * Encoding: Sortable base64 using ASCII-ordered alphabet:
 *   -0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz
 *
 * Properties:
 *   - 20 characters, URL-safe (same length as ES native IDs)
 *   - Lexicographic sort = chronological order across processes
 *   - Zero collisions: timestamp + sequence + random process ID
 *   - ~16M IDs per ms per process before sequence wraps
 *   - Timestamp extractable for analytics (bytes 0-5)
 *
 * @internal
 */
class TimeOrderedUUIDGenerator
{
    private static int $sequenceNumber;

    private static int $lastTimestamp = 0;

    private static string $processId;

    private static int $initPid = 0;

    private const STANDARD_B64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    private const SORTABLE_B64 = '-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz';

    private static function initialize(): void
    {
        if (self::$initPid === getmypid()) {
            return;
        }

        self::$sequenceNumber = random_int(0, 0xFFFFFF);
        self::$processId = random_bytes(6);
        self::$lastTimestamp = 0;
        self::$initPid = getmypid();
    }

    public static function generate(): string
    {
        self::initialize();

        $sequenceId = ++self::$sequenceNumber & 0xFFFFFF;

        $timestamp = max(self::$lastTimestamp, self::getTimestampMillis());

        if ($sequenceId === 0) {
            $timestamp++;
        }

        self::$lastTimestamp = $timestamp;

        // 15-byte structure: timestamp BE (6) + sequence BE (3) + process ID (6)
        $bytes = '';
        $bytes .= chr(($timestamp >> 40) & 0xFF);
        $bytes .= chr(($timestamp >> 32) & 0xFF);
        $bytes .= chr(($timestamp >> 24) & 0xFF);
        $bytes .= chr(($timestamp >> 16) & 0xFF);
        $bytes .= chr(($timestamp >> 8) & 0xFF);
        $bytes .= chr($timestamp & 0xFF);
        $bytes .= chr(($sequenceId >> 16) & 0xFF);
        $bytes .= chr(($sequenceId >> 8) & 0xFF);
        $bytes .= chr($sequenceId & 0xFF);
        $bytes .= self::$processId;

        return self::encodeSortableBase64($bytes);
    }

    public static function isValid(string $id): bool
    {
        if (strlen($id) !== 20) {
            return false;
        }

        $ms = self::extractTimestampRaw($id);

        // 2020-01-01 to 2100-01-01 in ms
        return $ms >= 1577836800000 && $ms <= 4102444800000;
    }

    public static function extractTimestamp(string $id): ?int
    {
        if (! self::isValid($id)) {
            return null;
        }

        return self::extractTimestampRaw($id);
    }

    public static function extractDateTime(string $id): ?\DateTimeImmutable
    {
        $ms = self::extractTimestamp($id);

        if ($ms === null) {
            return null;
        }

        $seconds = intdiv($ms, 1000);
        $microseconds = ($ms % 1000) * 1000;

        $dt = \DateTimeImmutable::createFromFormat('U', (string) $seconds);

        return $dt->modify("+{$microseconds} microseconds");
    }

    private static function extractTimestampRaw(string $id): int
    {
        $bytes = self::decodeSortableBase64($id);

        return (ord($bytes[0]) << 40)
             | (ord($bytes[1]) << 32)
             | (ord($bytes[2]) << 24)
             | (ord($bytes[3]) << 16)
             | (ord($bytes[4]) << 8)
             | ord($bytes[5]);
    }

    private static function getTimestampMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private static function encodeSortableBase64(string $bytes): string
    {
        $encoded = rtrim(base64_encode($bytes), '=');
        $urlSafe = strtr($encoded, '+/', '-_');

        return strtr($urlSafe, self::STANDARD_B64, self::SORTABLE_B64);
    }

    private static function decodeSortableBase64(string $encoded): string
    {
        $standard = strtr($encoded, self::SORTABLE_B64, self::STANDARD_B64);

        return base64_decode(strtr($standard, '-_', '+/'));
    }
}
