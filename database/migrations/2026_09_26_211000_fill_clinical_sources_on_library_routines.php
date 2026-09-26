<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Omple les guies clíniques de referència de les 11 rutines de la biblioteca. Són les que va
// recollir l'informe docs/informe-biblioteca-rutines.md; els enllaços es van comprovar (cap dona 404;
// alguns editors com AJKD, CGH, OUP o Obesity Canada bloquegen les consultes automàtiques però
// s'obren bé en un navegador). Cal que un dietista-nutricionista revisi que cada guia és la
// adequada per a la rutina abans d'usar-les com a referència clínica.
return new class extends Migration
{
    // patró de nom de la rutina => [[nom, url], ...]
    private const SOURCES = [
        'Reflux gastroesofàgic%' => [
            ['ACG — GERD guideline', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC8754510/'],
            ['GerdQ questionnaire', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC9390063/'],
        ],
        'Rutina per restrenyiment%' => [
            ['Rome IV — functional constipation criteria', 'https://theromefoundation.org/rome-iv/rome-iv-criteria/'],
            ['WGO 2025 — constipation guideline', 'https://www.worldgastroenterology.org/UserFiles/file/guidelines/constipation-english-2025.pdf'],
        ],
        'SII%' => [
            ['Monash — 3 phases of the low-FODMAP diet', 'http://www.monashfodmap.com/blog/3-phases-low-fodmap-diet/'],
            ['NICE CG61 — irritable bowel syndrome', 'https://www.nice.org.uk/guidance/cg61/chapter/Recommendations'],
            ['IBS-SSS severity score', 'https://bio-protocol.org/bio101/r9658697'],
        ],
        'Celiaquia%' => [
            ['ESPGHAN 2019 — coeliac disease guideline', 'https://www.espghan.org/knowledge-center/publications/Gastroenterology/2019_ESPGHAN_guidelines_for_diagnosing_coeliac_disease'],
            ['CDAT — Celiac Dietary Adherence Test', 'https://www.cghjournal.org/article/S1542-3565(09)00008-1/fulltext'],
        ],
        'Diabetis%' => [
            ['ADA — Standards of Care 2026', 'https://www.guidelinecentral.com/guideline/14119/'],
        ],
        'Malaltia renal crònica%' => [
            ['KDOQI 2020 — nutrition in CKD', 'https://www.ajkd.org/article/S0272-6386(20)30726-5/fulltext'],
        ],
        'Nutrició esportiva%' => [
            ['ACSM / AND / DC 2016 — joint position statement', 'https://pubmed.ncbi.nlm.nih.gov/26891166/'],
            ['IOC 2023 — REDs consensus', 'https://pubmed.ncbi.nlm.nih.gov/38325885/'],
        ],
        'Patró DASH%' => [
            ['ESC 2024 — hypertension guideline (summary)', 'https://www.practicenurse.co.uk/guidelines/esc-hypertension-guidelines-2024-guideline-in-a-nutshell'],
        ],
        'Control de colesterol%' => [
            ['ESC/EAS 2019 — dyslipidaemia guideline', 'https://www.escardio.org/guidelines/clinical-practice-guidelines/all-esc-practice-guidelines/dyslipidaemias-management/'],
            ['ESC/EAS — 2025 update', 'https://academic.oup.com/eurheartj/article/46/42/4359/8234482'],
            ['MEDAS / PREDIMED', 'https://pmc.ncbi.nlm.nih.gov/articles/PMC7601687/'],
        ],
        'Patró saludable%' => [
            ['Obesity Canada — adult clinical practice guideline', 'https://obesitycanada.ca/healthcare-professionals/adult-clinical-practice-guideline/'],
            ['FESNAD-SEEDO consensus', 'https://scielo.isciii.es/scielo.php?pid=S0212-16112012000300018&script=sci_arttext&tlng=es'],
            ['SEEDO 2024', 'https://scielo.isciii.es/scielo.php?script=sci_arttext&pid=S0212-16112024001100001'],
        ],
        'Postoperatori%' => [
            ['ASMBS — nutritional guidelines (2016 update)', 'https://asmbs.org/wp-content/uploads/2017/06/ASMBS-Nutritional-Guidelines-2016-Update.pdf'],
        ],
    ];

    public function up(): void
    {
        foreach (self::SOURCES as $pattern => $sources) {
            $list = array_map(fn ($s) => ['name' => $s[0], 'url' => $s[1]], $sources);

            DB::table('mst_library_routines')
                ->where('name', 'like', $pattern)
                ->update(['clinicalSources' => json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
    }

    public function down(): void
    {
        DB::table('mst_library_routines')->update(['clinicalSources' => null]);
    }
};
