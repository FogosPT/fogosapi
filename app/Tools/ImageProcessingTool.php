<?php

namespace App\Tools;

use Carbon\Carbon;
use Intervention\Image\ImageManager;
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;

class ImageProcessingTool
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";
    private const MAX_LONG_EDGE = 2560;
    private const JPEG_QUALITY  = 82;

    public static function isPng(string $bytes): bool
    {
        return strncmp($bytes, self::PNG_SIGNATURE, 8) === 0;
    }

    /**
     * Extract structured EXIF data from a PNG eXIf chunk.
     * Returns null if no eXIf chunk or no GPS lat/lng present.
     *
     * @return array{gps: array, taken_at: ?Carbon, raw: array}|null
     */
    public static function extractPngExif(string $bytes): ?array
    {
        $exifBlob = self::findExifChunk($bytes);
        if ($exifBlob === null) {
            return null;
        }

        try {
            $tiff = new PelTiff();
            $tiff->load(new PelDataWindow($exifBlob));
        } catch (\Throwable $e) {
            return null;
        }

        $ifd0    = $tiff->getIfd();
        $gpsIfd  = $ifd0?->getSubIfd(PelIfd::GPS);
        $exifIfd = $ifd0?->getSubIfd(PelIfd::EXIF);

        if ($gpsIfd === null) {
            return null;
        }

        $lat = self::readGpsCoord($gpsIfd, PelTag::GPS_LATITUDE,  PelTag::GPS_LATITUDE_REF,  ['S']);
        $lng = self::readGpsCoord($gpsIfd, PelTag::GPS_LONGITUDE, PelTag::GPS_LONGITUDE_REF, ['W']);

        if ($lat === null || $lng === null) {
            return null;
        }

        $altitude = self::readRational($gpsIfd, PelTag::GPS_ALTITUDE);
        $altRef   = self::readEntryValue($gpsIfd, PelTag::GPS_ALTITUDE_REF);
        if ($altitude !== null && $altRef !== null && (int) $altRef === 1) {
            $altitude = -$altitude;
        }

        $heading = self::readRational($gpsIfd, PelTag::GPS_IMG_DIRECTION);

        $takenAt = null;
        if ($exifIfd !== null) {
            $rawDt = self::readEntryValue($exifIfd, PelTag::DATE_TIME_ORIGINAL);
            if (is_string($rawDt) && $rawDt !== '') {
                try {
                    $takenAt = Carbon::createFromFormat('Y:m:d H:i:s', $rawDt, config('app.timezone'))?->utc();
                } catch (\Throwable $e) {
                    $takenAt = null;
                }
            }
        }

        return [
            'gps' => [
                'lat'         => $lat,
                'lng'         => $lng,
                'altitude_m'  => $altitude,
                'heading_deg' => $heading,
            ],
            'taken_at' => $takenAt,
            'raw'      => self::serializeIfds($ifd0),
        ];
    }

    /**
     * Resize, strip metadata, and encode the input bytes as JPEG.
     *
     * @return array{bytes: string, width: int, height: int, size: int}
     */
    public static function process(string $bytes): array
    {
        $manager = ImageManager::gd();
        $image   = $manager->read($bytes);

        $w = $image->width();
        $h = $image->height();
        if (max($w, $h) > self::MAX_LONG_EDGE) {
            if ($w >= $h) {
                $image->scale(width: self::MAX_LONG_EDGE);
            } else {
                $image->scale(height: self::MAX_LONG_EDGE);
            }
        }

        $encoded = $image->toJpeg(self::JPEG_QUALITY, progressive: true);
        $jpegBytes = (string) $encoded;

        return [
            'bytes'  => $jpegBytes,
            'width'  => $image->width(),
            'height' => $image->height(),
            'size'   => strlen($jpegBytes),
        ];
    }

    private static function findExifChunk(string $bytes): ?string
    {
        if (!self::isPng($bytes)) {
            return null;
        }
        $len    = strlen($bytes);
        $offset = 8;

        while ($offset + 8 <= $len) {
            $chunkLen  = unpack('N', substr($bytes, $offset, 4))[1];
            $chunkType = substr($bytes, $offset + 4, 4);
            $dataStart = $offset + 8;
            $dataEnd   = $dataStart + $chunkLen;
            if ($dataEnd + 4 > $len) {
                return null;
            }
            if ($chunkType === 'eXIf') {
                return substr($bytes, $dataStart, $chunkLen);
            }
            if ($chunkType === 'IEND') {
                return null;
            }
            $offset = $dataEnd + 4;
        }

        return null;
    }

    private static function readGpsCoord(PelIfd $gpsIfd, int $tag, int $refTag, array $negativeRefs): ?float
    {
        $entry = $gpsIfd->getEntry($tag);
        if ($entry === null) {
            return null;
        }
        $rationals = $entry->getValue();
        if (!is_array($rationals) || count($rationals) < 3) {
            return null;
        }
        [$d, $m, $s] = $rationals;
        $deg = self::rationalToFloat($d);
        $min = self::rationalToFloat($m);
        $sec = self::rationalToFloat($s);
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }
        $value = $deg + ($min / 60) + ($sec / 3600);

        $refEntry = $gpsIfd->getEntry($refTag);
        if ($refEntry !== null) {
            $ref = trim((string) $refEntry->getValue());
            if (in_array($ref, $negativeRefs, true)) {
                $value = -$value;
            }
        }

        return $value;
    }

    private static function readRational(PelIfd $ifd, int $tag): ?float
    {
        $entry = $ifd->getEntry($tag);
        if ($entry === null) {
            return null;
        }
        return self::rationalToFloat($entry->getValue());
    }

    private static function rationalToFloat(mixed $rational): ?float
    {
        if (!is_array($rational) || count($rational) < 2) {
            return null;
        }
        $denominator = (int) $rational[1];
        if ($denominator === 0) {
            return null;
        }
        return ((int) $rational[0]) / $denominator;
    }

    private static function readEntryValue(PelIfd $ifd, int $tag): mixed
    {
        $entry = $ifd->getEntry($tag);
        return $entry?->getValue();
    }

    private static function serializeIfds(?PelIfd $ifd): array
    {
        if ($ifd === null) {
            return [];
        }
        $out = [];
        foreach ($ifd->getEntries() as $entry) {
            $name = PelTag::getName($ifd->getType(), $entry->getTag());
            $val  = $entry->getValue();
            $out[$name] = is_scalar($val) ? $val : json_decode(json_encode($val), true);
        }
        foreach ($ifd->getSubIfds() as $sub) {
            $out['sub_' . PelIfd::getTypeName($sub->getType())] = self::serializeIfds($sub);
        }
        return $out;
    }
}
