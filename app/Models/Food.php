<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Food extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'mst_foods';

    public static $snakeAttributes = false;

    public $timestamps = false;

    protected $appends = ['macroCategory', 'subcategoryName', 'allergens'];

    protected $fillable = [
        'name', 'slug', 'description', 'imageUrl', 'categoryId', 'subcategoryId', 'ciqualCode',
        'standardGrams', 'servingDescription', 'calories',
        'proteinGrams', 'fatGrams', 'saturatedFatGrams', 'monounsaturatedFatGrams', 'polyunsaturatedFatGrams',
        'carbsGrams', 'sugarsGrams', 'starchGrams', 'fiberGrams',
        'sodiumMg', 'saltGrams', 'calciumMg', 'magnesiumMg', 'phosphorusMg', 'potassiumMg', 'zincMg', 'ironMg',
        'copperMg', 'manganeseMg', 'seleniumMcg',
        'vitaminAMcg', 'vitaminBMcg', 'vitaminDMcg', 'vitaminEMg', 'vitaminKMcg', 'vitaminCMg',
        'vitaminB1Mg', 'vitaminB2Mg', 'vitaminB3Mg', 'vitaminB5Mg', 'vitaminB6Mg', 'vitaminB9Mcg', 'vitaminB12Mcg',
        'nutricionistaId', 'supersededByFoodId', 'actiu', 'origenFont', 'observacions', 'etiquetes',
    ];

    protected function casts(): array
    {
        return [
            'standardGrams' => 'integer',
            'calories' => 'float',
            'proteinGrams' => 'float',
            'fatGrams' => 'float',
            'saturatedFatGrams' => 'float',
            'monounsaturatedFatGrams' => 'float',
            'polyunsaturatedFatGrams' => 'float',
            'carbsGrams' => 'float',
            'fiberGrams' => 'float',
            'sugarsGrams' => 'float',
            'starchGrams' => 'float',
            'sodiumMg' => 'float',
            'saltGrams' => 'float',
            'calciumMg' => 'float',
            'magnesiumMg' => 'float',
            'phosphorusMg' => 'float',
            'potassiumMg' => 'float',
            'zincMg' => 'float',
            'copperMg' => 'float',
            'manganeseMg' => 'float',
            'seleniumMcg' => 'float',
            'ironMg' => 'float',
            'actiu' => 'boolean',
            'vitaminAMcg' => 'float',
            'vitaminBMcg' => 'float',
            'vitaminDMcg' => 'float',
            'vitaminEMg' => 'float',
            'vitaminKMcg' => 'float',
            'vitaminCMg' => 'float',
            'vitaminB1Mg' => 'float',
            'vitaminB2Mg' => 'float',
            'vitaminB3Mg' => 'float',
            'vitaminB5Mg' => 'float',
            'vitaminB6Mg' => 'float',
            'vitaminB9Mcg' => 'float',
            'vitaminB12Mcg' => 'float',
        ];
    }

    // Macronutrient que més calories aporta d'aquesta ració (la fibra es tracta a part,
    // ja que és un component dels hidrats i no un macronutrient energètic per si sol: es
    // marca com a categoria quan la ració n'és especialment rica, per davant del macro
    // majoritari en calories).
    public function getMacroCategoryAttribute(): ?string
    {
        if ($this->fiberGrams === null) {
            return null;
        }
        if ($this->fiberGrams >= 5) {
            return 'fibra';
        }

        $kcalFromProtein = (float) $this->proteinGrams * 4;
        $kcalFromCarbs = (float) $this->carbsGrams * 4;
        $kcalFromFat = (float) $this->fatGrams * 9;

        $max = max($kcalFromProtein, $kcalFromCarbs, $kcalFromFat);
        if ($max <= 0) {
            return null;
        }
        if ($max === $kcalFromProtein) {
            return 'proteïnes';
        }
        if ($max === $kcalFromFat) {
            return 'greixos';
        }

        return 'hidrats de carboni';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodCategory::class, 'categoryId');
    }

    public function routines(): HasMany
    {
        return $this->hasMany(RoutineTemplateFood::class, 'foodId');
    }

    public function libraryRoutines(): HasMany
    {
        return $this->hasMany(LibraryRoutineFood::class, 'foodId');
    }

    public function nutricionista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutricionistaId');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MstSubcategory::class, 'subcategoryId');
    }

    public function allergenLinks(): HasMany
    {
        return $this->hasMany(FoodAllergen::class, 'foodId');
    }

    // Nom de la subcategoria (només informatiu, mai per cercar/filtrar) en català, amb
    // reserva al francès d'origen. Cal haver fet eager load de subcategory.translation
    // (si no, cada aliment dispararia una consulta pròpia).
    public function getSubcategoryNameAttribute(): ?string
    {
        return $this->subcategory?->translation?->displayName;
    }

    // Llista d'al·lergens de l'aliment: [{codi, nom, confianca}]. Cal eager load
    // allergenLinks.allergen.translation.
    public function getAllergensAttribute(): array
    {
        return $this->allergenLinks->map(fn (FoodAllergen $link) => [
            'codi' => $link->allergen?->codi,
            'nom' => $link->allergen?->translation?->displayName,
            'confianca' => $link->confianca,
        ])->filter(fn ($a) => $a['codi'] !== null)->values()->all();
    }

    // Biblioteca pública (nutricionistaId NULL) +, si es dona un nutricionista, els seus
    // aliments personalitzats. Sempre exclou versions substituïdes (ja no es poden triar
    // de nou, però les files es conserven perquè els registres antics hi continuen apuntant).
    // $onlyActive=true (per defecte) amaga els aliments personalitzats marcats "Inactiu" —
    // pensat per als selectors (registrar àpats), no per a la pàgina de gestió del propi
    // nutricionista, que ha de poder veure'ls i reactivar-los.
    public function scopeVisibleTo(Builder $query, ?string $nutricionistaId, bool $onlyActive = true): Builder
    {
        return $query->whereNull('mst_foods.supersededByFoodId')->where(function (Builder $q) use ($nutricionistaId) {
            $q->whereNull('mst_foods.nutricionistaId');
            if ($nutricionistaId) {
                $q->orWhere('mst_foods.nutricionistaId', $nutricionistaId);
            }
        })->when($onlyActive, fn (Builder $q) => $q->where('mst_foods.actiu', true));
    }

    // Un aliment personalitzat només es pot eliminar (i s'edita amb versionat en lloc
    // d'in-situ) si ja s'ha fet servir: en un registre diari d'aliments (food_log) o en la
    // llista d'aliments d'una rutina (plantilla activa o de biblioteca).
    public function isUsedInAnyRecord(): bool
    {
        $usedInDailyRecords = DB::table('reg_daily_records')
            ->where('fieldName', 'food_log')
            ->where('value', 'like', '%"'.$this->id.'"%')
            ->exists();

        return $usedInDailyRecords || $this->routines()->exists() || $this->libraryRoutines()->exists();
    }
}
