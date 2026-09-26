<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Text d'ajuda dels camps de la biblioteca de rutines (què vol dir cada extrem d'una escala, què és
// una ració, com mesurar la pressió...). Es mostra al pacient sota el camp en lloc de la frase genèrica
// del tipus de camp. Els omple a la biblioteca i també a les plantilles existents que en venen
// (mateix nom i etiqueta) i encara no en tenen: un text d'ajuda escrit pel nutricionista no es toca.
// Les traduccions d'aquests textos són al frontend (frontend/src/lib/libraryTexts.ts) i coincideixen
// amb el text exacte: si es canvia aquí, cal canviar-lo allà.
// Esborrany a revisar per un dietista-nutricionista.
return new class extends Migration
{
    // [nom del camp, etiquetes on s'aplica (actual i antiga), text d'ajuda]
    private const HELPS = [
        ['symptoms', ['Símptomes digestius (0-10)'], '0 = cap símptoma digestiu; 10 = símptomes molt intensos que et limiten el dia.'],
        ['heartburn', ['Cremor o acidesa (0-10)'], '0 = cap cremor; 10 = cremor o acidesa molt intensa que no et deixa fer vida normal.'],
        ['straining', ['Esforç per evacuar (0-10)'], '0 = evacues sense cap esforç; 10 = esforç molt gran o no pots evacuar.'],
        ['pain', ['Dolor o inflor (0-10)'], '0 = cap dolor ni inflor; 10 = dolor o inflor molt intensos.'],
        ['bloating', ['Inflor (0-10)'], '0 = cap inflor; 10 = abdomen molt inflat i tens.'],
        ['pain', ['Dolor abdominal (0-10)'], '0 = cap dolor; 10 = dolor abdominal molt intens.'],
        ['stress', ['Estrès (0-10)'], '0 = cap estrès; 10 = el màxim estrès que has sentit.'],
        ['carb_portion', ['Ració d\'hidrats (0-10)'], 'Valora quants hidrats de carboni has menjat en aquest àpat: 0 = cap; 10 = una ració molt abundant. En el mètode del plat, els hidrats ocupen aproximadament una quarta part del plat.'],
        ['salt_choices', ['Eleccions baixes en sodi'], '0 = has triat sobretot aliments salats o processats; 10 = has triat sempre opcions baixes en sal. Com més alt, millor.'],
        ['sodium_choices', ['Eleccions baixes en sodi'], '0 = has triat sobretot aliments salats o processats; 10 = has triat sempre opcions baixes en sal. Com més alt, millor.'],
        ['perceived_effort', ['Esforç percebut (0-10)'], 'Escala Borg CR-10 de l\'esforç de la sessió: 0 = repòs; 3 = moderat; 5 = fort; 7 = molt fort; 10 = esforç màxim.'],
        ['hunger', ['Gana percebuda (1-10)', 'Gana percebuda (0-10)'], '1 = gens de gana; 10 = una gana molt intensa. Valora-la abans de menjar.'],
        ['tolerance', ['Tolerància a l\'àpat (0-10)'], '0 = no has tolerat l\'àpat (nàusees, dolor o vòmits); 10 = l\'has tolerat perfectament. Com més alt, millor.'],
        ['phase', ['Fase de textura'], 'Tria la fase de textura que t\'hagi indicat l\'equip de cirurgia o el dietista.'],
        ['bristol', ['Tipus de femta (escala de Bristol)'], 'L\'escala de Bristol descriu la forma de la femta: 1-2 indica restrenyiment, 3-4 és normal i 5-7 tendeix cap a la diarrea.'],
        ['bp', ['Pressió arterial (si està indicada)'], 'Mesura-la asseguda i en repòs, després de 5 minuts de calma. Anota primer la sistòlica (alta) i després la diastòlica (baixa).'],
        ['glucose_before', ['Glucosa abans (si està indicada)'], 'Valor del glucòmetre en mg/dL abans de l\'àpat. Només si el teu equip t\'ho ha indicat.'],
        ['glucose_after', ['Glucosa 2 h després (si està indicada)'], 'Valor del glucòmetre en mg/dL 2 hores després de començar l\'àpat. Només si el teu equip t\'ho ha indicat.'],
        ['gluten_free_meals', ['Àpats 100% sense gluten'], 'Compta els àpats del dia (esmorzar, dinar, sopar, berenar…) que han estat 100% sense gluten.'],
        ['healthy_fats', ['Racions de greixos saludables (oli d\'oliva, fruits secs..)'], 'Una ració és, per exemple, una cullerada d\'oli d\'oliva o un grapat de fruits secs.'],
        ['fiber_portions', ['Racions riques en fibra soluble'], 'Per exemple: civada, llegums, fruita amb pell o verdura.'],
        ['saturated_fat_choices', ['Eleccions altes en greix saturat'], 'Compta les vegades que has triat aliments rics en greix saturat (embotits, mantega, brioixeria, carn grassa, fregits).'],
        ['activity_minutes', ['Activitat física', 'Activitat física (minuts)'], 'Suma els minuts d\'activitat física del dia, com caminar a bon ritme, anar en bicicleta o fer esport.'],
        ['activity_minutes', ['Activitat moderada', 'Activitat moderada (minuts)'], 'Minuts d\'activitat que fan pujar una mica el pols però et deixen parlar, com caminar ràpid o pedalar suau.'],
        ['fluid_intake', ['Líquids ingerits (aproximat)', 'Líquids ingerits (ml aproximats)'], 'Suma tots els líquids del dia (aigua, infusions, sopes, llet…). Un got són uns 200 ml.'],
        ['protein_portions', ['Racions de proteïna'], 'Compta les racions de carn, peix, ous, làctics o llegums. Segueix la pauta del teu dietista.'],
        ['hydration', ['Hidratació (aproximada)', 'Hidratació (litres aproximats)'], 'Suma els litres de líquid que has begut al llarg del dia, aproximadament.'],
        ['sleep_hours', ['Hores de son'], 'Hores dormides la nit anterior.'],
        ['fruit_veg', ['Racions de fruita i verdura'], 'Una ració és, per exemple, una peça de fruita mitjana o un plat de verdura.'],
        ['fruit_vegetables', ['Racions de fruita i verdura'], 'Una ració és, per exemple, una peça de fruita mitjana o un plat de verdura.'],
        ['whole_grains', ['Racions de cereals integrals'], 'Una ració és, per exemple, una llesca de pa integral o mig plat d\'arròs o pasta integral.'],
        ['processed_food', ['Processats consumits'], 'Compta els aliments processats que has consumit (embotits, snacks, plats precuinats, sopes de sobre…).'],
        ['weight', ['Pes', 'Pes (kg)'], 'Pesa\'t sempre en les mateixes condicions, per exemple al matí, en dejú i després d\'anar al lavabo.'],
        ['meals', ['Àpats planificats complerts'], 'Àpats del dia que has fet seguint el pla (esmorzar, dinar, sopar…).'],
        ['water', ['Aigua', 'Aigua (gots)'], 'Un got equival a uns 200-250 ml.'],
        ['protein_intake', ['Proteïna consumida (aproximat)', 'Proteïna consumida (g aproximats)'], 'Suma els grams de proteïna del dia (carn, peix, ous, làctics, batuts proteics). Segueix l\'objectiu que t\'hagi indicat l\'equip.'],
        ['fluid_intake', ['Líquids (aproximat)', 'Líquids (ml aproximats)'], 'Suma tots els líquids del dia, a glops petits i segons les indicacions del teu equip.'],
        ['bowel_movements', ['Deposicions (número)'], 'Nombre de vegades que has anat de ventre durant el dia.'],
        ['fiber', ['Racions riques en fibra'], 'Compta les racions d\'aliments rics en fibra (fruita, verdura, llegums, cereals integrals).'],
        ['activity', ['Moviment', 'Moviment (minuts)'], 'Suma els minuts que t\'has mogut durant el dia (caminar, bicicleta, exercici suau).'],
        ['cross_contact', ['Possible contacte creuat amb gluten'], 'Marca «Sí» si creus que has menjat alguna cosa que ha pogut tocar gluten (mateixa planxa, oli, utensilis…).'],
        ['hypo', ['Símptomes d\'hipoglucèmia'], 'Marca «Sí» si has notat tremolor, suor freda, mareig o gana intensa.'],
        ['plate', ['Composició del plat'], 'Anota què has menjat i com has distribuït el plat: la meitat verdures, una quarta part proteïna i una quarta part cereals o midó.'],
    ];

    public function up(): void
    {
        foreach (self::HELPS as [$name, $labels, $help]) {
            DB::table('mst_library_routine_fields')
                ->where('name', $name)->whereIn('label', $labels)
                ->where(fn ($q) => $q->whereNull('helpText')->orWhere('helpText', ''))
                ->update(['helpText' => $help]);

            DB::table('mst_routine_fields')
                ->where('name', $name)->whereIn('label', $labels)
                ->where(fn ($q) => $q->whereNull('helpText')->orWhere('helpText', ''))
                ->update(['helpText' => $help]);
        }
    }

    public function down(): void
    {
        foreach (self::HELPS as [$name, $labels, $help]) {
            foreach (['mst_library_routine_fields', 'mst_routine_fields'] as $table) {
                DB::table($table)
                    ->where('name', $name)->whereIn('label', $labels)->where('helpText', $help)
                    ->update(['helpText' => null]);
            }
        }
    }
};
