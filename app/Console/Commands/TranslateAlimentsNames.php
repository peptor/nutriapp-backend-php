<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Omple els noms per idioma (nom_ca, nom_es, nom_eu, nom_gl, nom_pt, nom_it, nom_en) que
 * l'origen deixa buits, traduint el nom francès original amb l'API gratuïta de MyMemory.
 * Funciona amb els aliments i categories de Ciqual (mst_aliments, mst_categories, taules
 * dedicades) i també amb qualsevol entitat guardada a la taula genèrica mst_translate
 * (aliments i subcategories de la biblioteca pròpia, mst_foods/mst_subcategory) — es tria
 * amb --table. És un procés llarg i el servei gratuït té una quota diària de paraules, així
 * que està pensat per parar-se i reprendre's sense problemes: només tradueix files on la
 * columna de destí encara és NULL.
 *
 * Ús:
 *   php artisan ciqual:translate-names --table=aliments --lang=ca
 *   php artisan ciqual:translate-names --table=categories --lang=ca
 *   php artisan ciqual:translate-names --table=foods --lang=ca
 *   php artisan ciqual:translate-names --table=subcategories --lang=ca
 *   php artisan ciqual:translate-names --lang=ca --limit=100   (prova ràpida)
 */
class TranslateAlimentsNames extends Command
{
    protected $signature = 'ciqual:translate-names
        {--table=aliments : Taula a traduir (aliments, categories, foods, subcategories)}
        {--lang= : Codi d\'idioma a omplir (ca, es, eu, gl, pt, it, en)}
        {--limit= : Màxim de files a traduir en aquesta execució}
        {--sleep=200 : Mil·lisegons d\'espera entre crides a l\'API (per no saturar el servei gratuït)}';

    protected $description = "Tradueix els noms d'aliments i categories (francès -> idioma de l'app) amb l'API gratuïta de MyMemory";

    private const SUPPORTED_LANGS = ['ca', 'es', 'eu', 'gl', 'pt', 'it', 'en'];

    /**
     * Per cada taula: la columna amb el nom francès original i, si cal, un filtre fix
     * (les entitats de mst_translate hi conviuen totes barrejades per entityType).
     */
    private const TABLES = [
        'aliments' => ['table' => 'mst_aliments', 'source' => 'nom_original_fr', 'where' => []],
        'categories' => ['table' => 'mst_categories', 'source' => 'nom_original', 'where' => []],
        'foods' => ['table' => 'mst_translate', 'source' => 'nom_fr', 'where' => ['entityType' => 'food']],
        'subcategories' => ['table' => 'mst_translate', 'source' => 'nom_fr', 'where' => ['entityType' => 'subcategory']],
    ];

    public function handle(): int
    {
        $tableKey = (string) $this->option('table');
        if (! isset(self::TABLES[$tableKey])) {
            $this->error("--table ha de ser un de: ".implode(', ', array_keys(self::TABLES)));

            return self::FAILURE;
        }

        $table = self::TABLES[$tableKey]['table'];
        $sourceColumn = self::TABLES[$tableKey]['source'];
        $extraWhere = self::TABLES[$tableKey]['where'];

        $lang = (string) $this->option('lang');
        if (! in_array($lang, self::SUPPORTED_LANGS, true)) {
            $this->error("--lang ha de ser un de: ".implode(', ', self::SUPPORTED_LANGS));

            return self::FAILURE;
        }

        $column = 'nom_'.$lang;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $sleepMs = (int) $this->option('sleep');

        $query = DB::table($table)->where($extraWhere)->whereNull($column)->whereNotNull($sourceColumn);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info("Tots els registres de {$table} ja tenen {$column} omplert.");

            return self::SUCCESS;
        }

        $this->info("Traduint {$table}.{$column} ({$total} pendents".($limit ? ", límit {$limit} aquesta execució" : '').")...");

        $done = 0;
        $failed = 0;
        $quotaExhausted = false;

        $query->chunkById(200, function ($rows) use ($table, $tableKey, $sourceColumn, $column, $lang, $sleepMs, $limit, &$done, &$failed, &$quotaExhausted) {
            foreach ($rows as $row) {
                if ($quotaExhausted || ($limit && ($done + $failed) >= $limit)) {
                    return false;
                }

                $translated = $this->translate($row->{$sourceColumn}, $lang);

                if ($translated === 'QUOTA') {
                    $quotaExhausted = true;
                    $this->warn('Quota diària de MyMemory exhaurida. Atura aquí; torna a executar més tard per continuar.');

                    return false;
                }

                if ($translated === null) {
                    $failed++;
                } else {
                    DB::table($table)->where('id', $row->id)->update([$column => $translated]);
                    // mst_foods.name es manté sincronitzat amb nom_ca fins que tots els
                    // consumidors (frontend inclòs) resolguin el nom via mst_translate.
                    if ($tableKey === 'foods' && $lang === 'ca') {
                        DB::table('mst_foods')->where('id', $row->entityId)->update(['name' => $translated]);
                    }
                    $done++;
                }

                if (($done + $failed) % 100 === 0) {
                    $this->line("  {$done} traduïts, {$failed} fallits...");
                }

                usleep($sleepMs * 1000);
            }
        }, 'id');

        $this->info("Fet: {$done} traduïts, {$failed} fallits.".($quotaExhausted ? ' (aturat per quota, reprèn més tard amb la mateixa comanda)' : ''));

        return self::SUCCESS;
    }

    private function translate(string $text, string $targetLang): ?string
    {
        try {
            // Aquest PHP de Windows no té cap bundle de CA configurat (curl.cainfo buit a
            // php.ini), així que qualsevol crida HTTPS des de PHP falla amb "unable to get
            // local issuer certificate". És un problema conegut d'instal·lacions locals de
            // PHP a Windows, no d'aquesta app; en lloc de tocar el php.ini del sistema
            // (fora del projecte), es desactiva la verificació només per aquesta crida a
            // una API pública que no rep ni retorna cap dada sensible.
            //
            // NOTA: sense ->retry() a propòsit. Amb ->retry(), el client HTTP de Laravel
            // llença una RequestException per a qualsevol resposta que no sigui 2xx un cop
            // exhaurits els intents, en lloc de simplement retornar-la — i un 429 de quota
            // no es resol reintentant, així que això només amagava l'estat real darrere
            // d'una excepció genèrica que el catch convertia sempre en "fallit".
            $response = Http::withOptions(['verify' => false])->timeout(10)->get('https://api.mymemory.translated.net/get', [
                'q' => $text,
                'langpair' => 'fr|'.$targetLang,
                'de' => 'peptor@gmail.com',
            ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            return $e->response?->status() === 429 ? 'QUOTA' : null;
        } catch (\Throwable $e) {
            return null;
        }

        if ($response->status() === 429) {
            return 'QUOTA';
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $status = $data['responseStatus'] ?? null;

        if ($status == 403 || $status == 429 || (is_string($data['responseDetails'] ?? null) && str_contains(strtolower($data['responseDetails']), 'quota'))) {
            return 'QUOTA';
        }

        $translated = $data['responseData']['translatedText'] ?? null;
        if (! is_string($translated) || $translated === '') {
            return null;
        }

        // MyMemory a vegades torna el mateix text sense traduir quan no troba res millor
        // (típic amb noms propis o quan la quota just s'exhaureix): ho acceptem igualment,
        // és millor que deixar-ho buit, i sempre es podrà refer manualment més endavant.
        return $translated;
    }
}
