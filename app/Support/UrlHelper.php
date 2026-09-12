<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

// Els fitxers pujats (logo, foto de pacient) es desen amb una ruta relativa al disc
// públic; cal convertir-los a absoluta perquè el frontend (un origen diferent) els
// pugui carregar directament en una etiqueta <img>.
class UrlHelper
{
    public static function toAbsoluteUrl(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        return Storage::disk('public')->url($relativePath);
    }
}
