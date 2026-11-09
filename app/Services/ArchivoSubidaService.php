<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;


class ArchivoSubidaService
{
    private const ANCHO_MAXIMO = 1600;

    private const ALTO_MAXIMO = 1600;

    private const CALIDAD_JPEG = 80;

    
    public function guardar(UploadedFile $archivo, string $carpeta, string $disco = 'local'): string|false
    {
        if ($this->esImagen($archivo)) {
            return $this->guardarImagenComprimida($archivo, $carpeta, $disco);
        }

        return $archivo->store($carpeta, $disco);
    }

    private function esImagen(UploadedFile $archivo): bool
    {
        return str_starts_with((string) $archivo->getMimeType(), 'image/');
    }

    private function guardarImagenComprimida(UploadedFile $archivo, string $carpeta, string $disco): string|false
    {
        try {
            $manager = new ImageManager(new Driver);

            
            
            $imagen = $manager->decode($archivo->getRealPath())
                ->scaleDown(width: self::ANCHO_MAXIMO, height: self::ALTO_MAXIMO);

            $contenido = (string) $imagen->encode(new JpegEncoder(quality: self::CALIDAD_JPEG));

            $ruta = $carpeta.'/'.Str::random(40).'.jpg';

            if (! Storage::disk($disco)->put($ruta, $contenido)) {
                return false;
            }

            return $ruta;
        } catch (Throwable $e) {
            Log::warning('No se pudo comprimir la imagen subida, se guarda el original sin tocar.', [
                'archivo' => $archivo->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $archivo->store($carpeta, $disco);
        }
    }
}