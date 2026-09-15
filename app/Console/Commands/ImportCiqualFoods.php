<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use XMLReader;
use SimpleXMLElement;

/**
 * Importa la biblioteca nutricional Ciqual 2025 (Anses) a les taules mst_* d'aquesta app.
 * Adaptat de l'script proporcionat (import_ciqual.php): mateixa lògica de lectura dels
 * quatre XML oficials, però fent servir la connexió Laravel (sense credencials pròpies)
 * i amb detecció de canvis per no reescriure files que ja tenen el mateix valor —
 * pensat perquè es pugui repetir la importació quan Ciqual publiqui una revisió, sense
 * malgastar temps reescrivint els ~260.000 valors nutricionals que no hagin canviat.
 *
 * Ús:
 *   php artisan ciqual:import --dir=/ruta/als/xml
 *   php artisan ciqual:import --dir=/ruta/als/xml --dry-run
 */
class ImportCiqualFoods extends Command
{
    protected $signature = 'ciqual:import
        {--dir= : Carpeta amb els 4 XML oficials de Ciqual 2025}
        {--dry-run : Només comprova que els fitxers existeixen, no importa res}
        {--skip-photos : No intentis enllaçar fotos de la biblioteca "mst_foods" al final}';

    protected $description = "Importa (o actualitza) la biblioteca nutricional Ciqual 2025 a les taules mst_*";

    private const CIQUAL_VERSION = '2025';
    private const CIQUAL_PREFIX = '2025_11_03';
    private const CIQUAL_URL = 'https://ciqual.anses.fr/';
    private const CIQUAL_LICENSE = 'Etalab Open License 2.0';

    private array $categoryMap = [];
    private array $nutrientMap = [];

    public function handle(): int
    {
        // La comparació de canvis a importComposition() precarrega ~260.000 files a
        // memòria per no haver de reescriure-les si no han canviat; el límit per defecte
        // de PHP (128M) es queda curt per a això.
        ini_set('memory_limit', '512M');

        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/ciqual')), DIRECTORY_SEPARATOR);
        $dryRun = (bool) $this->option('dry-run');

        $files = [
            'groups' => $dir.'/alim_grp_'.self::CIQUAL_PREFIX.'.xml',
            'foods' => $dir.'/alim_'.self::CIQUAL_PREFIX.'.xml',
            'const' => $dir.'/const_'.self::CIQUAL_PREFIX.'.xml',
            'compo' => $dir.'/compo_'.self::CIQUAL_PREFIX.'.xml',
        ];

        foreach ($files as $type => $file) {
            if (! is_file($file)) {
                $this->error("Falta el fitxer {$type}: {$file}");
                $this->line('Descarrega els 4 XML oficials de Ciqual 2025 (ciqual.anses.fr) i indica la carpeta amb --dir=');

                return self::FAILURE;
            }
        }

        // Bonus opcional: bibliografia de cada source_code. No és un dels 4 XML "oficials"
        // del pla original, però si hi és, l'important per completar la traçabilitat.
        $sourcesFile = $dir.'/sources_'.self::CIQUAL_PREFIX.'.xml';

        if ($dryRun) {
            $this->info('DRY-RUN: els 4 fitxers s\'han trobat correctament.');
            foreach ($files as $type => $file) {
                $this->line(sprintf('  %-8s %s (%s bytes)', $type, $file, number_format(filesize($file))));
            }

            return self::SUCCESS;
        }

        $fontId = $this->upsertFont();

        if (is_file($sourcesFile)) {
            $this->info('Important bibliografia de fonts (sources)...');
            $sourcesCount = $this->importSources($sourcesFile);
            $this->info("  {$sourcesCount} referències.");
        }

        $this->info('Important grups i categories...');
        $this->importGroups($files['groups']);
        $this->info('  '.count($this->categoryMap).' categories.');

        $this->info('Important constituents/nutrients...');
        $constCount = $this->importNutrients($files['const']);
        $this->info("  {$constCount} nutrients.");

        $this->info('Important aliments (només es reescriuen els que han canviat)...');
        [$foodCount, $foodChanged] = $this->importFoods($files['foods'], $fontId);
        $this->info("  {$foodCount} aliments processats, {$foodChanged} nous o actualitzats.");

        $foodIdMap = DB::table('mst_aliments')
            ->where('font_id', $fontId)
            ->pluck('id', 'external_id')
            ->all();

        $this->info('Important composició nutricional (pot trigar uns minuts la primera vegada)...');
        [$compoCount, $compoChanged, $compoSkipped] = $this->importComposition($files['compo'], $foodIdMap, $fontId);
        $this->info("  {$compoCount} valors processats, {$compoChanged} nous o actualitzats, {$compoSkipped} files ignorades (aliment/nutrient no trobat).");

        DB::table('mst_fonts_dades')->where('id', $fontId)->update(['data_importacio' => now()]);

        if (! $this->option('skip-photos')) {
            $this->info('Enllaçant fotos de la biblioteca pròpia (foods) quan el nom coincideix...');
            $linked = $this->linkOwnPhotos();
            $this->info("  {$linked} aliments Ciqual enllaçats amb una foto ja existent.");
        }

        $this->info('Importació de Ciqual finalitzada.');

        return self::SUCCESS;
    }

    /**
     * Llegeix els registres (fills directes de l'arrel) d'un XML de Ciqual sense carregar
     * tot el fitxer a memòria — imprescindible per compo_*.xml (desenes de MB).
     */
    private function xmlRecords(string $file, callable $callback): int
    {
        $reader = new XMLReader();
        if (! $reader->open($file, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new \RuntimeException("No es pot obrir XML: {$file}");
        }

        $count = 0;

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth !== 1) {
                continue;
            }

            $outer = $reader->readOuterXml();
            if ($outer === '') {
                continue;
            }

            $node = @simplexml_load_string($outer, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if ($node === false) {
                throw new \RuntimeException("XML invàlid dins de {$file}");
            }

            $record = [];
            foreach ($node->children() as $child) {
                $record[$child->getName()] = trim((string) $child);
            }

            if ($record !== []) {
                $callback($record);
                $count++;
            }
        }

        $reader->close();

        return $count;
    }

    private function field(array $record, string $name): ?string
    {
        if (! array_key_exists($name, $record)) {
            return null;
        }
        $v = trim((string) $record[$name]);

        return $v === '' ? null : $v;
    }

    private function normalizeNumber(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(["\u{00A0}", ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * Ciqual distingeix "-" (sense dada), "traces" i "<10" (valor per sota del límit de
     * detecció) d'un valor real. Cap d'aquests casos s'ha de desar com a zero.
     */
    private function parseNumeric(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '-') {
            return [null, 'sense_dada'];
        }
        if (str_contains(mb_strtolower($raw, 'UTF-8'), 'trace')) {
            return [null, 'traces'];
        }
        if (str_starts_with($raw, '<')) {
            return [$this->normalizeNumber(substr($raw, 1)), 'menys_que'];
        }

        return [$this->normalizeNumber($raw), 'valor'];
    }

    private function inferUnit(string $name): string
    {
        if (preg_match('/µg|ug|microg/i', $name)) {
            return 'µg';
        }
        if (preg_match('/\bmg\b/i', $name)) {
            return 'mg';
        }
        if (preg_match('/\bkJ\b/i', $name)) {
            return 'kJ';
        }
        if (preg_match('/\bkcal\b/i', $name)) {
            return 'kcal';
        }

        return 'g';
    }

    private function inferGroup(string $name, string $infoods): string
    {
        $s = mb_strtolower($name.' '.$infoods, 'UTF-8');

        if (str_contains($s, 'énergie') || str_contains($s, 'energy') || str_contains($s, 'kcal') || str_contains($s, 'kj') || str_starts_with($infoods, 'ENERC')) {
            return 'energia';
        }
        if (str_contains($s, 'vitamine') || str_contains($s, 'vitamin') || preg_match('/\b(vita|vitb|vitc|vitd|vite|vitk|thia|ribf|nia|fol)/i', $infoods)) {
            return 'vitamina';
        }
        if (preg_match('/sodium|potassium|calcium|magnésium|magnesium|phosphore|phosphorus|\bfer\b|iron|zinc|cuivre|copper|manganèse|manganese|iode|iodine|selenium|sélénium/i', $s)) {
            return 'mineral';
        }
        if (preg_match('/lipide|lipid|acide gras|fatty acid|cholestérol|cholesterol/i', $s)) {
            return 'lipid';
        }
        if (preg_match('/protéine|protein|glucide|carbohydrate|fibre|fiber|amidon|starch|sucre|sugar|alcool|alcohol|\beau\b|water/i', $s)) {
            return 'macronutrient';
        }

        return 'altres';
    }

    private function catalanCategoryName(string $fr): string
    {
        static $map = [
            'Aliments et boissons' => 'Aliments i begudes',
            'Fruits, légumes et légumineuses' => 'Fruites, verdures i llegums',
            'Fruits' => 'Fruites',
            'Légumes' => 'Verdures',
            'Légumineuses' => 'Llegums',
            'Céréales' => 'Cereals',
            'Viandes' => 'Carns',
            'Poissons' => 'Peixos',
            'Produits laitiers' => 'Làctics',
            'Œufs' => 'Ous',
            'Matières grasses' => 'Greixos i olis',
            'Boissons' => 'Begudes',
        ];

        return $map[$fr] ?? $fr;
    }

    private function importSources(string $file): int
    {
        $count = 0;
        $batch = [];
        $this->xmlRecords($file, function (array $r) use (&$count, &$batch): void {
            $code = $this->field($r, 'source_code');
            if ($code === null) {
                return;
            }
            $batch[] = ['source_code' => $code, 'ref_citation' => $this->field($r, 'ref_citation')];
            $count++;
            if (count($batch) >= 500) {
                DB::table('mst_fonts_referencies')->upsert($batch, ['source_code'], ['ref_citation']);
                $batch = [];
            }
        });
        if ($batch !== []) {
            DB::table('mst_fonts_referencies')->upsert($batch, ['source_code'], ['ref_citation']);
        }

        return $count;
    }

    private function upsertFont(): int
    {
        $existing = DB::table('mst_fonts_dades')->where('nom', 'Ciqual')->where('versio', self::CIQUAL_VERSION)->first();
        if ($existing) {
            return $existing->id;
        }

        return DB::table('mst_fonts_dades')->insertGetId([
            'nom' => 'Ciqual',
            'organisme' => 'Anses - Observatoire des aliments',
            'versio' => self::CIQUAL_VERSION,
            'url' => self::CIQUAL_URL,
            'llicencia' => self::CIQUAL_LICENSE,
            'identificador_extern' => 'DOI:10.57745/RDMHWY',
            'created_at' => now(),
        ]);
    }

    private function categoryId(?string $externalCode, int $level, string $name, ?string $originalName, ?int $parentId, int $order): int
    {
        $existing = DB::table('mst_categories')->where('codi_extern', $externalCode)->where('nivell', $level)->first();
        if ($existing) {
            if ($existing->nom !== $name || $existing->nom_original !== $originalName || $existing->ordre !== $order) {
                DB::table('mst_categories')->where('id', $existing->id)->update([
                    'nom' => $name, 'nom_original' => $originalName, 'ordre' => $order,
                ]);
            }

            return $existing->id;
        }

        return DB::table('mst_categories')->insertGetId([
            'parent_id' => $parentId,
            'codi_extern' => $externalCode,
            'nivell' => $level,
            'nom' => $name,
            'nom_original' => $originalName,
            'ordre' => $order,
        ]);
    }

    private function importGroups(string $file): void
    {
        $this->xmlRecords($file, function (array $r): void {
            $g = $this->field($r, 'alim_grp_code');
            $sg = $this->field($r, 'alim_ssgrp_code');
            $ssg = $this->field($r, 'alim_ssssgrp_code');

            if ($g !== null) {
                $name = $this->catalanCategoryName($this->field($r, 'alim_grp_nom_fr') ?? 'Sense categoria');
                $this->categoryMap["G:$g"] = $this->categoryId("G:$g", 1, $name, $this->field($r, 'alim_grp_nom_fr'), null, (int) $g);
            }
            if ($g !== null && $sg !== null && trim($sg, '0') !== '') {
                $name = $this->catalanCategoryName($this->field($r, 'alim_ssgrp_nom_fr') ?? 'Sense subcategoria');
                $parent = $this->categoryMap["G:$g"] ?? null;
                $this->categoryMap["SG:$sg"] = $this->categoryId("SG:$sg", 2, $name, $this->field($r, 'alim_ssgrp_nom_fr'), $parent, (int) $sg);
            }
            if ($sg !== null && $ssg !== null && trim($ssg, '0') !== '') {
                $name = $this->catalanCategoryName($this->field($r, 'alim_ssssgrp_nom_fr') ?? 'Altres');
                $parent = $this->categoryMap["SG:$sg"] ?? null;
                $this->categoryMap["SSG:$ssg"] = $this->categoryId("SSG:$ssg", 3, $name, $this->field($r, 'alim_ssssgrp_nom_fr'), $parent, (int) $ssg);
            }
        });
    }

    /**
     * Codis de constituent de Ciqual (const_code) que fem correspondre als nutrients
     * "amigables" ja sembrats a la migració. NOMÉS aquests: Ciqual reutilitza el mateix
     * code_INFOODS genèric (p.ex. "ENERC") en diversos constituents que NO són el mateix
     * valor (energia per reglament UE vs. per factor de Jones, en kJ i en kcal alhora), de
     * manera que fer matching per infoods_code fusionaria constituents diferents en una
     * sola fila i en perdríem dades. Per això aquí el mapa és explícit per const_code.
     * Qualsevol altre constituent (la resta dels 74) es desa igualment, amb el seu propi
     * codi CIQUAL_<const_code>, sense fusionar-se amb cap altre.
     */
    private const FRIENDLY_NUTRIENT_CODES = [
        '328' => 'ENERGY_KCAL', '327' => 'ENERGY_KJ', '400' => 'WATER',
        '25000' => 'PROTEIN', '31000' => 'CARBS', '40000' => 'FAT', '34100' => 'FIBER',
        '32000' => 'SUGARS', '33110' => 'STARCH', '60000' => 'ALCOHOL',
        '10110' => 'SODIUM', '10190' => 'POTASSIUM', '10200' => 'CALCIUM',
        '10120' => 'MAGNESIUM', '10150' => 'PHOSPHORUS', '10260' => 'IRON',
        '10300' => 'ZINC', '51104' => 'VIT_A', '51200' => 'RETINOL',
        '51330' => 'BETA_CAROTENE', '56100' => 'VIT_B1', '56200' => 'VIT_B2',
        '56310' => 'VIT_B3', '56500' => 'VIT_B6', '56700' => 'VIT_B9',
        '56600' => 'VIT_B12', '55100' => 'VIT_C', '52100' => 'VIT_D',
        '53100' => 'VIT_E', '54101' => 'VIT_K', '75100' => 'CHOLESTEROL',
    ];

    private function importNutrients(string $file): int
    {
        $count = 0;
        $this->xmlRecords($file, function (array $r) use (&$count): void {
            $constCode = $this->field($r, 'const_code');
            if ($constCode === null) {
                return;
            }
            $fr = $this->field($r, 'const_nom_fr') ?? ('Constituent '.$constCode);
            $eng = $this->field($r, 'const_nom_eng') ?? '';
            $infoods = $this->field($r, 'code_INFOODS') ?? '';
            $unit = $this->inferUnit($fr.' '.$eng);
            $group = $this->inferGroup($fr, $infoods);
            $order = (int) $constCode;

            $code = self::FRIENDLY_NUTRIENT_CODES[$constCode] ?? ('CIQUAL_'.$constCode);

            // Els nutrients "amigables" sembrats a la migració encara no tenen font_code
            // (es vinculen ara, la primera vegada que es veu el seu const_code); per això
            // cal buscar també pel codi que els correspondria.
            $existing = DB::table('mst_nutrients')->where('font_code', $constCode)->orWhere('codi', $code)->first();

            $row = [
                'codi' => $code, 'nom' => $fr, 'nom_original_fr' => $fr, 'grup' => $group,
                'unitat' => $unit, 'infoods_code' => $infoods !== '' ? $infoods : null,
                'font_code' => $constCode, 'ordre' => $order,
            ];

            if ($existing) {
                // "font_code" també compta com a canvi encara que nom/unitat/ordre ja
                // coincideixin: si no, un nutrient sembrat que es va quedar sense
                // vincular no es podria arreglar mai en una represa posterior.
                $changed = ($existing->nom !== $row['nom']) || ($existing->unitat !== $row['unitat'])
                    || ((int) $existing->ordre !== $order) || ($existing->font_code !== $constCode);
                if ($changed) {
                    DB::table('mst_nutrients')->where('id', $existing->id)->update($row);
                }
                $id = $existing->id;
            } else {
                $id = DB::table('mst_nutrients')->insertGetId($row + ['created_at' => now()]);
            }

            $this->nutrientMap[$constCode] = $id;
            $count++;
        });

        return $count;
    }

    /** @return array{0:int,1:int} [processats, canviats] */
    private function importFoods(string $file, int $fontId): array
    {
        $existingByExternalId = DB::table('mst_aliments')
            ->where('font_id', $fontId)
            ->get(['id', 'external_id', 'nom', 'nom_eng', 'nom_cientific', 'categoria_id', 'factor_jones'])
            ->keyBy('external_id');

        $count = 0;
        $changed = 0;

        $this->xmlRecords($file, function (array $r) use ($fontId, $existingByExternalId, &$count, &$changed): void {
            $code = $this->field($r, 'alim_code');
            $name = $this->field($r, 'alim_nom_fr');
            if ($code === null || $name === null) {
                return;
            }

            $categoryId = null;
            $ssg = $this->field($r, 'alim_ssssgrp_code');
            $sg = $this->field($r, 'alim_ssgrp_code');
            $g = $this->field($r, 'alim_grp_code');
            if ($ssg !== null && trim($ssg, '0') !== '' && isset($this->categoryMap["SSG:$ssg"])) {
                $categoryId = $this->categoryMap["SSG:$ssg"];
            } elseif ($sg !== null && trim($sg, '0') !== '' && isset($this->categoryMap["SG:$sg"])) {
                $categoryId = $this->categoryMap["SG:$sg"];
            } elseif ($g !== null && isset($this->categoryMap["G:$g"])) {
                $categoryId = $this->categoryMap["G:$g"];
            }

            $row = [
                'nom' => $name,
                'nom_original_fr' => $name,
                'nom_eng' => $this->field($r, 'alim_nom_eng'),
                'nom_cientific' => $this->field($r, 'alim_nom_sci'),
                'categoria_id' => $categoryId,
                // El nom real del camp al XML de Ciqual 2025 és "facteur_Jones" (J
                // majúscula); es comprova també en minúscula per si una altra versió el
                // publica diferent.
                'factor_jones' => $this->normalizeNumber($this->field($r, 'facteur_Jones') ?? $this->field($r, 'facteur_jones')),
            ];

            $count++;
            $existing = $existingByExternalId->get($code);

            if ($existing) {
                $isSame = $existing->nom === $row['nom']
                    && $existing->nom_eng === $row['nom_eng']
                    && $existing->nom_cientific === $row['nom_cientific']
                    && (int) $existing->categoria_id === (int) ($row['categoria_id'] ?? 0)
                    && (float) $existing->factor_jones === (float) ($row['factor_jones'] ?? 0);
                if (! $isSame) {
                    DB::table('mst_aliments')->where('id', $existing->id)->update($row + ['updated_at' => now(), 'actiu' => true]);
                    $changed++;
                }

                return;
            }

            DB::table('mst_aliments')->insert($row + [
                'font_id' => $fontId,
                'external_id' => $code,
                'unitat_base' => 'g',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $changed++;
        });

        return [$count, $changed];
    }

    /** @return array{0:int,1:int,2:int} [processats, canviats, ignorats] */
    private function importComposition(string $file, array $foodIdMap, int $fontId): array
    {
        // Preload existing composition keyed by "alimentId:nutrientId" per comparar i no
        // reescriure el que no ha canviat (l'import complet pot ser ~260.000 files).
        $existing = [];
        DB::table('mst_aliment_nutrients')
            ->select('aliment_id', 'nutrient_id', 'valor', 'valor_original', 'qualificacio', 'valor_min', 'valor_max', 'codi_confianca', 'source_code')
            ->orderBy('aliment_id')
            ->chunk(5000, function ($rows) use (&$existing): void {
                foreach ($rows as $row) {
                    $existing[$row->aliment_id.':'.$row->nutrient_id] = $row;
                }
            });

        $count = 0;
        $changed = 0;
        $skipped = 0;
        $batch = [];

        $flush = function () use (&$batch): void {
            if ($batch === []) {
                return;
            }
            // upsert per lots: MySQL "INSERT ... ON DUPLICATE KEY UPDATE" via query builder.
            DB::table('mst_aliment_nutrients')->upsert(
                $batch,
                ['aliment_id', 'nutrient_id'],
                ['valor', 'valor_original', 'qualificacio', 'valor_min', 'valor_max', 'codi_confianca', 'font_id', 'source_code', 'updated_at']
            );
            $batch = [];
        };

        $this->xmlRecords($file, function (array $r) use (
            $foodIdMap, &$count, &$changed, &$skipped, &$batch, $flush, $fontId, $existing
        ): void {
            $foodCode = $this->field($r, 'alim_code');
            $constCode = $this->field($r, 'const_code');

            if ($foodCode === null || $constCode === null || ! isset($foodIdMap[$foodCode]) || ! isset($this->nutrientMap[$constCode])) {
                $skipped++;

                return;
            }

            $alimentId = $foodIdMap[$foodCode];
            $nutrientId = $this->nutrientMap[$constCode];

            [$value, $qualifier] = $this->parseNumeric($this->field($r, 'teneur'));
            [$min] = $this->parseNumeric($this->field($r, 'min'));
            [$max] = $this->parseNumeric($this->field($r, 'max'));
            $confianca = $this->field($r, 'code_confiance');
            $sourceCode = $this->field($r, 'source_code');
            $original = $this->field($r, 'teneur');

            $count++;
            $key = $alimentId.':'.$nutrientId;
            $prev = $existing[$key] ?? null;

            if ($prev
                && (float) $prev->valor === (float) $value
                && $prev->qualificacio === $qualifier
                && (float) $prev->valor_min === (float) $min
                && (float) $prev->valor_max === (float) $max
                && $prev->codi_confianca === $confianca
                && $prev->source_code === $sourceCode) {
                return; // idèntic al que ja hi ha desat: no cal escriure res.
            }

            $changed++;
            $batch[] = [
                'aliment_id' => $alimentId,
                'nutrient_id' => $nutrientId,
                'valor' => $value,
                'valor_original' => $original,
                'qualificacio' => $qualifier,
                'valor_min' => $min,
                'valor_max' => $max,
                'codi_confianca' => $confianca,
                'font_id' => $fontId,
                'source_code' => $sourceCode,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 500) {
                $flush();
            }

            if ($count % 20000 === 0) {
                $this->line("  {$count} files llegides ({$changed} noves/actualitzades)...");
            }
        });

        $flush();

        return [$count, $changed, $skipped];
    }

    /**
     * Quan el nom d'un aliment Ciqual coincideix (en francès o anglès) amb un dels 97
     * aliments de la biblioteca pròpia (taula "mst_foods", en català), reutilitza la mateixa
     * icona en lloc de deixar l'aliment Ciqual sense foto. Coincidència raonable, no
     * exhaustiva: es fa servir un mapa curat nom_ca -> paraules clau en francès/anglès,
     * ja que Ciqual no dona el nom en català.
     *
     * NOMÉS assigna la icona, mai el nom: una mateixa icona (p.ex. "Vedella") pot
     * correspondre a desenes de variants Ciqual diferents ("Boeuf, entrecôte crue",
     * "Boeuf, steak haché 5% MG cuit"...), i sobreescriure nom_ca amb el nom genèric
     * de la icona destruiria aquest detall. El nom real el posa ciqual:translate-names
     * a partir de nom_original_fr.
     */
    private function linkOwnPhotos(): int
    {
        $keywordsByFoodName = [
            'Ceba' => ['oignon', 'onion'], 'Pebrot' => ['poivron', 'pepper'], 'Bròquil' => ['brocoli', 'broccoli'],
            'Carbassó' => ['courgette', 'zucchini'], 'Espinacs' => ['épinard', 'spinach'], 'All' => ['ail,', 'garlic'],
            'Pastanaga' => ['carotte', 'carrot'], 'Tomàquet' => ['tomate', 'tomato'], 'Amanida' => ['laitue', 'lettuce'],
            'Albergínia' => ['aubergine', 'eggplant'], 'Cogombre' => ['concombre', 'cucumber'],
            'Espàrrecs' => ['asperge', 'asparagus'], 'Carbassa' => ['potiron', 'courge', 'pumpkin'],
            'Poma' => ['pomme,', 'apple'], 'Síndria' => ['pastèque', 'watermelon'], 'Alvocat' => ['avocat', 'avocado'],
            'Meló' => ['melon,', 'melon'], 'Kiwi' => ['kiwi'], 'Llimona' => ['citron,', 'lemon'], 'Pera' => ['poire,', 'pear'],
            'Mango' => ['mangue', 'mango'], 'Nabius' => ['myrtille', 'blueberr'], 'Raïm' => ['raisin,', 'grape'],
            'Taronja' => ['orange,', 'orange,'], 'Préssec' => ['pêche,', 'peach'], 'Coco' => ['coco,', 'coconut'],
            'Castanyes' => ['châtaigne', 'chestnut'], 'Plàtan' => ['banane', 'banana'], 'Cireres' => ['cerise', 'cherry'],
            'Pinya' => ['ananas', 'pineapple'], 'Pa integral' => ['pain complet', 'wholemeal bread'],
            'Blat de moro' => ['maïs', 'corn'], 'Civada' => ['avoine', 'oat'], 'Arròs blanc' => ['riz blanc', 'white rice'],
            'Pasta integral' => ['pâtes complètes', 'wholewheat pasta'], 'Patata' => ['pomme de terre', 'potato'],
            'Pasta blanca' => ['pâtes,', 'pasta,'], 'Pa blanc' => ['pain blanc', 'white bread'],
            'Arròs integral' => ['riz complet', 'brown rice'], 'Quinoa' => ['quinoa'], 'Moniato' => ['patate douce', 'sweet potato'],
            "Oli d'oliva" => ['huile d\'olive', 'olive oil'], 'Mantega' => ['beurre', 'butter'],
            'Oli de gira-sol' => ['huile de tournesol', 'sunflower oil'], 'Faves' => ['fève', 'broad bean'],
            'Llenties' => ['lentille', 'lentil'], 'Mongetes' => ['haricot', 'bean,'], 'Pèsols' => ['petit pois', 'pea,'],
            'Cigrons' => ['pois chiche', 'chickpea'], 'Hummus' => ['houmous', 'hummus'],
            'Llet' => ['lait,', 'milk,'], 'Formatge' => ['fromage', 'cheese'], 'Iogurt natural' => ['yaourt nature', 'plain yog'],
            'Iogurt grec' => ['yaourt grec', 'greek yog'], 'Salmó' => ['saumon', 'salmon'], 'Tofu' => ['tofu'],
            'Pollastre' => ['poulet', 'chicken'], 'Vedella' => ['bœuf', 'boeuf', 'beef'], 'Porc' => ['porc,', 'pork'],
            'Ostres' => ['huître', 'oyster'], 'Gambes' => ['crevette', 'shrimp'], 'Ou' => ['œuf', 'oeuf', 'egg,'],
            'Xocolata' => ['chocolat noir', 'dark chocolate'], 'Cafè' => ['café,', 'coffee,'], 'Te' => ['thé,', 'tea,'],
            'Cervesa' => ['bière', 'beer'], 'Flam' => ['flan,', 'crème caramel'], 'Gelat' => ['crème glacée', 'ice cream'],
            'Sal' => ['sel de table', 'table salt'], 'Mel' => ['miel', 'honey'], 'Pizza' => ['pizza'],
            'Hamburguesa' => ['hamburger'], 'Sushi' => ['sushi'], 'Vi negre' => ['vin rouge', 'red wine'],
        ];

        $ownFoods = Food::whereNotNull('imageUrl')->get(['name', 'imageUrl'])->keyBy('name');
        $linked = 0;

        foreach ($keywordsByFoodName as $ownName => $keywords) {
            $own = $ownFoods->get($ownName);
            if (! $own) {
                continue;
            }

            $query = DB::table('mst_aliments')->whereNull('imatge_url');
            $query->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    // Ancorat a l'inici: Ciqual anomena els aliments "Base, descriptor"
                    // (p.ex. "Pomme, crue"), així que buscar-ho enmig del nom agafaria
                    // fals positius com "Nectar multifruit, base pomme, standard".
                    $q->orWhere('nom', 'like', $kw.'%')->orWhere('nom_eng', 'like', $kw.'%');
                }
            });

            $updated = $query->update(['imatge_url' => $own->imageUrl]);
            $linked += $updated;
        }

        return $linked;
    }
}
