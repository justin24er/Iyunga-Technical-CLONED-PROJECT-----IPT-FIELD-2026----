<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generates short, sortable, collision-resistant string IDs, in the same
 * spirit as Prisma's default `cuid()` (which the original schema used for
 * every primary key) — lowercase alphanumeric, prefixed with "c" so IDs
 * never look like they could be interpreted as numbers.
 *
 * Not cryptographically identical to the cuid2 spec, but same properties
 * that matter here: time-ordered prefix + random suffix, URL-safe, ~25
 * chars, effectively unique across a single application's lifetime.
 */
final class Id
{
    public static function generate(): string
    {
        $timePart = base_convert((string) (int) (microtime(true) * 1000), 10, 36);
        $randomPart = bin2hex(random_bytes(8)); // 16 hex chars

        $id = 'c' . $timePart . $randomPart;

        // Keep a stable, predictable length (pad/truncate to 25 chars).
        $id = substr(str_pad($id, 25, '0'), 0, 25);

        return strtolower($id);
    }
}
