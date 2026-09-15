<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\FoodFavorite;
use App\Models\FoodRecentSelection;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    // Biblioteca pública + els aliments personalitzats del propi nutricionista, per a la
    // pàgina de cerca i consulta (només nutricionista).
    public function index(Request $request)
    {
        $foods = Food::with(['category', 'subcategory.translation', 'allergenLinks.allergen.translation'])
            ->visibleTo($request->user()->id)
            ->join('mst_food_categories', 'mst_foods.categoryId', '=', 'mst_food_categories.id')
            ->orderBy('mst_food_categories.name')
            ->orderBy('mst_foods.name')
            ->select('mst_foods.*')
            ->get();

        return response()->json($foods);
    }

    private function validateFoodData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'categoryId' => 'required|string|exists:mst_food_categories,id',
            'standardGrams' => 'required|integer|min:1',
            'servingDescription' => 'required|string|max:80',
            'calories' => 'required|numeric|min:0',
            'proteinGrams' => 'required|numeric|min:0',
            'fatGrams' => 'required|numeric|min:0',
            'carbsGrams' => 'required|numeric|min:0',
            'fiberGrams' => 'required|numeric|min:0',
            'sodiumMg' => 'required|numeric|min:0',
            'calciumMg' => 'required|numeric|min:0',
            'ironMg' => 'required|numeric|min:0',
            'vitaminAMcg' => 'required|numeric|min:0',
            'vitaminBMcg' => 'required|numeric|min:0',
        ]);
    }

    // Aliment personalitzat, privat del nutricionista que el crea (mai el veuen altres
    // nutricionistes ni els pacients).
    public function storeFood(Request $request)
    {
        $data = $this->validateFoodData($request);

        $food = Food::create(array_merge($data, [
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'nutricionistaId' => $request->user()->id,
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

        if ($food->isUsedInAnyRecord()) {
            $newFood = Food::create(array_merge($data, [
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'nutricionistaId' => $food->nutricionistaId,
                'imageUrl' => $food->imageUrl,
            ]));
            $food->update(['supersededByFoodId' => $newFood->id]);

            return response()->json($newFood->load('category'));
        }

        $food->update($data);

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
