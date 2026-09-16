<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\FoodFavorite;
use App\Models\FoodRecentSelection;
use App\Models\Patient;
use App\Support\UrlHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Favorits ("Els meus aliments") i recents del picker d'aliments. Els comparteixen el
// pacient (sobre ell mateix) i el seu nutricionista (indicant ?patientId=), igual que la
// resta de gestió de dades de seguiment — d'aquí el mateix patró de resolució d'accés
// que RecordsController::resolveAssignmentForWrite però a nivell de pacient, no d'assignació.
class FoodLibraryController extends Controller
{
    private function resolvePatientId(Request $request): array
    {
        $user = $request->user();
        if ($user->role === 'PACIENT') {
            $patient = Patient::where('userId', $user->id)->first();
            if (! $patient) {
                return [null, response()->json(['error' => 'Perfil de pacient no trobat'], 404)];
            }

            return [$patient->id, null];
        }

        $patientId = $request->input('patientId') ?? $request->query('patientId');
        if (! $patientId) {
            return [null, response()->json(['error' => 'Falta patientId'], 400)];
        }
        $patient = Patient::find($patientId);
        if (! $patient || $patient->nutricionistaId !== $user->id) {
            return [null, response()->json(['error' => 'Accés denegat'], 403)];
        }

        return [$patientId, null];
    }

    public function favorites(Request $request)
    {
        [$patientId, $error] = $this->resolvePatientId($request);
        if ($error) {
            return $error;
        }

        $foods = Food::with(['category', 'subcategory.translation', 'allergenLinks.allergen.translation'])
            ->join('reg_food_favorites', 'reg_food_favorites.foodId', '=', 'mst_foods.id')
            ->where('reg_food_favorites.patientId', $patientId)
            ->orderBy('mst_foods.name')
            ->select('mst_foods.*')
            ->get();

        return response()->json($foods);
    }

    public function addFavorite(Request $request)
    {
        [$patientId, $error] = $this->resolvePatientId($request);
        if ($error) {
            return $error;
        }

        $data = $request->validate(['foodId' => 'required|string|exists:mst_foods,id']);

        FoodFavorite::firstOrCreate(['patientId' => $patientId, 'foodId' => $data['foodId']]);
        $this->touchRecent($patientId, $data['foodId']);

        return response()->json(['ok' => true], 201);
    }

    public function removeFavorite(Request $request, string $foodId)
    {
        [$patientId, $error] = $this->resolvePatientId($request);
        if ($error) {
            return $error;
        }

        FoodFavorite::where('patientId', $patientId)->where('foodId', $foodId)->delete();

        return response()->json(['ok' => true]);
    }

    public function recents(Request $request)
    {
        [$patientId, $error] = $this->resolvePatientId($request);
        if ($error) {
            return $error;
        }

        $foods = Food::with(['category', 'subcategory.translation', 'allergenLinks.allergen.translation'])
            ->join('reg_food_recent_selections', 'reg_food_recent_selections.foodId', '=', 'mst_foods.id')
            ->where('reg_food_recent_selections.patientId', $patientId)
            ->orderByDesc('reg_food_recent_selections.lastSelectedAt')
            ->limit(20)
            ->select('mst_foods.*')
            ->get();

        return response()->json($foods);
    }

    // El frontend crida això cada cop que s'afegeix un aliment (a un àpat o als favorits),
    // per poder alimentar la pestanya "Recents" sense escanejar tot el food_log.
    public function recordSelection(Request $request)
    {
        [$patientId, $error] = $this->resolvePatientId($request);
        if ($error) {
            return $error;
        }

        $data = $request->validate(['foodId' => 'required|string|exists:mst_foods,id']);
        $this->touchRecent($patientId, $data['foodId']);

        return response()->json(['ok' => true]);
    }

    private function touchRecent(string $patientId, string $foodId): void
    {
        FoodRecentSelection::updateOrCreate(
            ['patientId' => $patientId, 'foodId' => $foodId],
            ['lastSelectedAt' => Carbon::now()],
        );
    }

    // Biblioteca pública + els aliments personalitzats del propi nutricionista (actius o
    // no: aquí es gestionen, així que també ha de poder veure i reactivar els inactius),
    // per a la pàgina de cerca i consulta (només nutricionista).
    public function index(Request $request)
    {
        $foods = Food::with(['category', 'subcategory.translation', 'allergenLinks.allergen.translation'])
            ->visibleTo($request->user()->id, onlyActive: false)
            ->join('mst_food_categories', 'mst_foods.categoryId', '=', 'mst_food_categories.id')
            ->orderBy('mst_food_categories.name')
            ->orderBy('mst_foods.name')
            ->select('mst_foods.*')
            ->get();

        return response()->json($foods);
    }

    private const ALLOWED_IMAGE_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    // Nom, categoria i pes estàndard són l'únic imprescindible: la resta (ració, calories,
    // tots els nutrients, metadades) és opcional perquè un nutricionista pugui registrar un
    // plat casolà de seguida i completar-ne els detalls més endavant si vol.
    private function validateFoodData(Request $request): array
    {
        $numericOptional = 'nullable|numeric|min:0';

        return $request->validate([
            'name' => 'required|string|max:255',
            'categoryId' => 'required|string|exists:mst_food_categories,id',
            'standardGrams' => 'required|integer|min:1',
            'servingDescription' => 'nullable|string|max:80',
            'actiu' => 'nullable|boolean',
            'calories' => $numericOptional,
            'proteinGrams' => $numericOptional,
            'fatGrams' => $numericOptional,
            'saturatedFatGrams' => $numericOptional,
            'monounsaturatedFatGrams' => $numericOptional,
            'polyunsaturatedFatGrams' => $numericOptional,
            'carbsGrams' => $numericOptional,
            'sugarsGrams' => $numericOptional,
            'starchGrams' => $numericOptional,
            'fiberGrams' => $numericOptional,
            'sodiumMg' => $numericOptional,
            'calciumMg' => $numericOptional,
            'ironMg' => $numericOptional,
            'magnesiumMg' => $numericOptional,
            'phosphorusMg' => $numericOptional,
            'potassiumMg' => $numericOptional,
            'zincMg' => $numericOptional,
            'copperMg' => $numericOptional,
            'manganeseMg' => $numericOptional,
            'seleniumMcg' => $numericOptional,
            'vitaminAMcg' => $numericOptional,
            'vitaminB1Mg' => $numericOptional,
            'vitaminB2Mg' => $numericOptional,
            'vitaminB3Mg' => $numericOptional,
            'vitaminB5Mg' => $numericOptional,
            'vitaminB6Mg' => $numericOptional,
            'vitaminB9Mcg' => $numericOptional,
            'vitaminB12Mcg' => $numericOptional,
            'vitaminCMg' => $numericOptional,
            'vitaminDMcg' => $numericOptional,
            'vitaminEMg' => $numericOptional,
            'vitaminKMcg' => $numericOptional,
            'origenFont' => 'nullable|string|max:255',
            'observacions' => 'nullable|string|max:300',
            'etiquetes' => 'nullable|string|max:255',
        ]);
    }

    // La imatge és opcional i es guarda com a URL absoluta (no relativa com el logo
    // d'empresa): a diferència d'aquell, que només es llegeix des d'un sol endpoint,
    // imageUrl d'un aliment es fa servir tal qual arreu de l'app (selectors, targetes...),
    // així que convertir-lo un sol cop aquí evita haver-ho de fer a cada lectura.
    private function handleImageUpload(Request $request, ?string $previousImageUrl): ?string
    {
        $file = $request->file('image');
        if (! $file) {
            return $previousImageUrl;
        }

        $ext = self::ALLOWED_IMAGE_MIME[$file->getMimeType()] ?? null;
        if (! $ext) {
            abort(response()->json(['error' => "Format d'imatge no vàlid. Usa PNG, JPG o WEBP."], 400));
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            abort(response()->json(['error' => 'La imatge no pot superar els 2 MB'], 400));
        }

        $filename = 'custom-food-'.now()->getTimestampMs().'-'.Str::lower(Str::random(6)).'.'.$ext;
        $path = $file->storeAs('food-photos', $filename, 'public');

        return UrlHelper::toAbsoluteUrl($path);
    }

    // Aliment personalitzat, privat del nutricionista que el crea (mai el veuen altres
    // nutricionistes ni els pacients).
    public function storeFood(Request $request)
    {
        $data = $this->validateFoodData($request);
        $imageUrl = $this->handleImageUpload($request, null);

        $food = Food::create(array_merge($data, [
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'nutricionistaId' => $request->user()->id,
            'imageUrl' => $imageUrl,
        ]));

        return response()->json($food->load('category'), 201);
    }

    // Si l'aliment ja s'ha usat en algun registre, no es modifica in situ: es crea una
    // versió nova (que és la que es podrà triar a partir d'ara) i l'antiga es marca com a
    // substituïda, però es conserva perquè els registres que ja hi apunten no canviïn.
    public function updateFood(Request $request, string $id)
    {
        $food = Food::find($id);
        if (! $food || $food->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }
        if ($food->supersededByFoodId) {
            return response()->json(['error' => 'Aquest aliment ja té una versió més recent'], 400);
        }

        $data = $this->validateFoodData($request);
        $imageUrl = $this->handleImageUpload($request, $food->imageUrl);

        if ($food->isUsedInAnyRecord()) {
            $newFood = Food::create(array_merge($data, [
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'nutricionistaId' => $food->nutricionistaId,
                'imageUrl' => $imageUrl,
            ]));
            $food->update(['supersededByFoodId' => $newFood->id]);

            return response()->json($newFood->load('category'));
        }

        $food->update(array_merge($data, ['imageUrl' => $imageUrl]));

        return response()->json($food->load('category'));
    }

    public function destroyFood(Request $request, string $id)
    {
        $food = Food::find($id);
        if (! $food || $food->nutricionistaId !== $request->user()->id) {
            return response()->json(['error' => 'Accés denegat'], 403);
        }
        if ($food->isUsedInAnyRecord()) {
            return response()->json(['error' => "No es pot eliminar: aquest aliment ja s'ha usat en algun registre."], 409);
        }

        $food->delete();

        return response()->json(['ok' => true]);
    }
}
