<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_videoforum\local;

/**
 * Small, dependency-free duration probe for common MP4/WebM uploads.
 *
 * It is intentionally conservative: if reliable container metadata cannot be
 * read, null is returned and the upload layer falls back to its other checks.
 *
 * @package mod_videoforum
 */
class media_probe {
    public static function duration(string $path, string $mimetype): ?float {
        if ($mimetype === 'video/mp4') {
            return self::mp4_duration($path);
        }
        if ($mimetype === 'video/webm') {
            return self::webm_duration($path);
        }
        return null;
    }

    private static function mp4_duration(string $path): ?float {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return null;
        }

        try {
            $filesize = filesize($path);
            $offset = 0;
            while ($offset + 8 <= $filesize) {
                fseek($handle, $offset);
                $header = fread($handle, 8);
                if (strlen($header) !== 8) {
                    break;
                }
                $size = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);
                $headersize = 8;

                if ($size === 1) {
                    $extended = fread($handle, 8);
                    if (strlen($extended) !== 8) {
                        break;
                    }
                    $parts = unpack('Nhigh/Nlow', $extended);
                    $size = ($parts['high'] * 4294967296) + $parts['low'];
                    $headersize = 16;
                } else if ($size === 0) {
                    $size = $filesize - $offset;
                }

                if ($size < $headersize) {
                    break;
                }
                if ($type === 'moov') {
                    return self::find_mvhd($handle, $offset + $headersize, $size - $headersize);
                }
                $offset += $size;
            }
        } finally {
            fclose($handle);
        }
        return null;
    }

    private static function find_mvhd($handle, int $start, int $length): ?float {
        $offset = $start;
        $end = $start + $length;

        while ($offset + 8 <= $end) {
            fseek($handle, $offset);
            $header = fread($handle, 8);
            if (strlen($header) !== 8) {
                return null;
            }
            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            if ($size < 8 || $offset + $size > $end) {
                return null;
            }

            if ($type === 'mvhd') {
                $payload = fread($handle, min(40, $size - 8));
                if (strlen($payload) < 20) {
                    return null;
                }
                $version = ord($payload[0]);
                if ($version === 0 && strlen($payload) >= 20) {
                    $timescale = unpack('N', substr($payload, 12, 4))[1];
                    $duration = unpack('N', substr($payload, 16, 4))[1];
                    return $timescale > 0 ? $duration / $timescale : null;
                }
                if ($version === 1 && strlen($payload) >= 32) {
                    $timescale = unpack('N', substr($payload, 20, 4))[1];
                    $parts = unpack('Nhigh/Nlow', substr($payload, 24, 8));
                    $duration = ($parts['high'] * 4294967296) + $parts['low'];
                    return $timescale > 0 ? $duration / $timescale : null;
                }
                return null;
            }
            $offset += $size;
        }
        return null;
    }

    private static function webm_duration(string $path): ?float {
        $data = @file_get_contents($path, false, null, 0, 2 * 1024 * 1024);
        if ($data === false || $data === '') {
            return null;
        }

        $timescale = 1000000.0;
        $scaleoffset = strpos($data, "\x2A\xD7\xB1");
        if ($scaleoffset !== false) {
            $cursor = $scaleoffset + 3;
            $size = self::read_ebml_size($data, $cursor);
            if ($size !== null && $size > 0 && $size <= 8 && $cursor + $size <= strlen($data)) {
                $value = 0;
                for ($i = 0; $i < $size; $i++) {
                    $value = ($value << 8) | ord($data[$cursor + $i]);
                }
                if ($value > 0) {
                    $timescale = (float)$value;
                }
            }
        }

        $durationoffset = strpos($data, "\x44\x89");
        if ($durationoffset === false) {
            return null;
        }
        $cursor = $durationoffset + 2;
        $size = self::read_ebml_size($data, $cursor);
        if ($size === 4 && $cursor + 4 <= strlen($data)) {
            $value = unpack('G', substr($data, $cursor, 4))[1];
        } else if ($size === 8 && $cursor + 8 <= strlen($data)) {
            $value = unpack('E', substr($data, $cursor, 8))[1];
        } else {
            return null;
        }

        $seconds = ((float)$value * $timescale) / 1000000000.0;
        return is_finite($seconds) && $seconds > 0 ? $seconds : null;
    }

    /**
     * Read an EBML variable-size integer and advance cursor.
     */
    private static function read_ebml_size(string $data, int &$cursor): ?int {
        if ($cursor >= strlen($data)) {
            return null;
        }

        $first = ord($data[$cursor]);
        $mask = 0x80;
        $length = 1;
        while ($length <= 8 && ($first & $mask) === 0) {
            $mask >>= 1;
            $length++;
        }
        if ($length > 8 || $cursor + $length > strlen($data)) {
            return null;
        }

        $value = $first & ($mask - 1);
        for ($i = 1; $i < $length; $i++) {
            $value = ($value << 8) | ord($data[$cursor + $i]);
        }
        $cursor += $length;
        return $value;
    }
}
