<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * An opaque sync cursor: a server-clock timestamp, never a client-supplied
 * one ("Do not rely on client clocks alone" — the client only ever echoes
 * back exactly what the server gave it last time, never constructs one
 * itself). Encoded rather than a bare ISO string so a future version can
 * add fields without breaking already-deployed Electron clients that just
 * pass it through opaquely.
 */
class SyncCursor
{
    public static function encode(\DateTimeInterface $at): string
    {
        return base64_encode(json_encode(['as_of' => Carbon::instance($at)->format('Y-m-d H:i:s.u')]));
    }

    /**
     * @throws InvalidArgumentException if the cursor is malformed — surfaced
     *  as a 422 by the controller, since a corrupted/tampered cursor should
     *  never silently resync everything (data flood) or nothing (silent gap).
     */
    public static function decode(string $cursor): Carbon
    {
        $decoded = json_decode(base64_decode($cursor, true) ?: '', true);

        if (! is_array($decoded) || ! isset($decoded['as_of'])) {
            throw new InvalidArgumentException('Invalid sync cursor.');
        }

        try {
            return Carbon::parse($decoded['as_of']);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid sync cursor.');
        }
    }
}
