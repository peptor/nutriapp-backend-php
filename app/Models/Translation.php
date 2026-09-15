<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

// Taula genèrica de noms per idioma (mst_translate): una fila = tots els noms d'una
// entitat (food, category, subcategory, allergen...), identificada per entityType+entityId
// (l'id/uuid propi de l'entitat a la seva taula d'origen).
class Translation extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_translate';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $fillable = [
        'entityType', 'entityId', 'nom_ca', 'nom_es', 'nom_eu', 'nom_gl', 'nom_pt', 'nom_it', 'nom_fr', 'nom_en',
    ];

    // Nom en català, amb reserva al francès (font original) si encara no hi ha traducció.
    public function getDisplayNameAttribute(): ?string
    {
        return $this->nom_ca ?? $this->nom_fr;
    }
}
