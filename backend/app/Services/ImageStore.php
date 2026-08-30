<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;

/**
 * Guarda imagenes pequenas (avatares, iconos de premio) dentro de la propia
 * base de datos, codificadas en base64.
 *
 * Render borra el disco en cada deploy, así que no sirve guardarlas en
 * storage/. Y base64 en una columna de texto evita el binding PARAM_LOB que
 * BYTEA exige en PostgreSQL.
 */
class ImageStore
{
    /** Tope por imagen. Aiven free da 1 GB: con esto caben miles. */
    public const MAX_BYTES = 200 * 1024;

    public const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * @return array{mime:string, data:string}
     */
    public function encode(UploadedFile $file): array
    {
        return [
            'mime' => $file->getMimeType(),
            'data' => base64_encode(file_get_contents($file->getRealPath())),
        ];
    }

    /**
     * Sirve la imagen con ETag para que el navegador no la vuelva a pedir:
     * las tablas muestran muchos avatares a la vez.
     */
    public function respond(?string $mime, ?string $base64): Response
    {
        if ($mime === null || $base64 === null) {
            return response('', 404);
        }

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return response('', 404);
        }

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => '"'.md5($base64).'"',
        ]);
    }
}
