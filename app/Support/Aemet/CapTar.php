<?php

namespace App\Support\Aemet;

/**
 * Reads the `avisos_cap` tar (GNU, uncompressed, one CAP XML per file).
 */
final class CapTar
{
    /**
     * @return CapMessage[]
     *
     * @throws \RuntimeException when the bytes are not a tar or any entry is not a CAP alert
     */
    public static function messages(string $tarBytes): array
    {
        // PharData picks the format by extension, so the temp file must end in .tar.
        $path = sys_get_temp_dir().'/aemet-cap-'.bin2hex(random_bytes(8)).'.tar';

        try {
            if (false === file_put_contents($path, $tarBytes)) {
                throw new \RuntimeException('AEMET CAP: could not write the temporary tar');
            }

            try {
                $tar = new \PharData($path);
                $entries = [];
                foreach (new \RecursiveIteratorIterator($tar) as $file) {
                    if ($file->isFile()) {
                        $entries[$file->getPathname()] = $file->getContent();
                    }
                }
                unset($tar);
            } catch (\Throwable $e) {
                throw new \RuntimeException('AEMET CAP: invalid tar ('.$e->getMessage().')', 0, $e);
            }

            $messages = [];
            foreach ($entries as $name => $xml) {
                try {
                    $messages[] = CapMessage::fromXml($xml);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('AEMET CAP: '.basename($name).': '.$e->getMessage(), 0, $e);
                }
            }

            return $messages;
        } finally {
            @unlink($path);
        }
    }
}
