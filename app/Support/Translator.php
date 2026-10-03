<?php

namespace App\Support;

// Traduccions dels textos que genera el SERVIDOR (correus, push, alertes): l'únic contingut de l'app que no passa
// pel diccionari del frontend (frontend/src/lib/i18n.tsx), perquè es redacta sense JavaScript (un correu, o una
// notificació push abans d'obrir l'app). Mateixos 5 idiomes seleccionables: ca, es, en, gl, eu.
//
// Limitació coneguda: el nom de la rutina, l'etiqueta d'un camp i el valor registrat (p. ex. "Cremor o acidesa",
// "8/10") venen de la BD i només existeixen en català — surten sense traduir dins la frase, encara que la resta
// del text sí que estigui en l'idioma de l'usuari.
class Translator
{
    private const STRINGS = [
        // ---------------- comuns a tots els correus ----------------
        'brand_tagline' => ['ca' => 'El teu acompanyant intel·ligent', 'es' => 'Tu acompañante inteligente', 'en' => 'Your intelligent companion', 'gl' => 'O teu acompañante intelixente', 'eu' => 'Zure laguntzaile adimenduna'],
        'greeting' => ['ca' => 'Hola{name},', 'es' => 'Hola{name},', 'en' => 'Hi{name},', 'gl' => 'Ola{name},', 'eu' => 'Kaixo{name},'],
        'footer_automated' => ['ca' => 'Aquest és un correu automàtic, si us plau no hi responguis.', 'es' => 'Este es un correo automático, por favor no respondas.', 'en' => 'This is an automated email, please do not reply.', 'gl' => 'Este é un correo automático, por favor non respondas.', 'eu' => 'Hau posta elektroniko automatiko bat da, mesedez ez erantzun.'],

        // ---------------- recuperar contrasenya ----------------
        'password_reset_subject' => ['ca' => 'Recupera la teva contrasenya', 'es' => 'Recupera tu contraseña', 'en' => 'Reset your password', 'gl' => 'Recupera o teu contrasinal', 'eu' => 'Berreskuratu zure pasahitza'],
        'password_reset_intro' => ['ca' => 'Hem rebut una sol·licitud per restablir la contrasenya del teu compte. Per continuar, fes clic al botó següent:', 'es' => 'Hemos recibido una solicitud para restablecer la contraseña de tu cuenta. Para continuar, haz clic en el siguiente botón:', 'en' => 'We received a request to reset your account password. To continue, click the button below:', 'gl' => 'Recibimos unha solicitude para restabelecer o contrasinal da túa conta. Para continuar, fai clic no seguinte botón:', 'eu' => 'Zure kontuaren pasahitza berrezartzeko eskaera bat jaso dugu. Jarraitzeko, klikatu beheko botoia:'],
        'password_reset_button' => ['ca' => 'Restablir contrasenya', 'es' => 'Restablecer contraseña', 'en' => 'Reset password', 'gl' => 'Restabelecer contrasinal', 'eu' => 'Berrezarri pasahitza'],
        'password_reset_expiry' => ['ca' => 'Per motius de seguretat, aquest enllaç només és vàlid durant {minutes} minuts. Un cop transcorregut aquest temps, hauràs de tornar a sol·licitar-ne un de nou.', 'es' => 'Por motivos de seguridad, este enlace solo es válido durante {minutes} minutos. Pasado ese tiempo, tendrás que solicitar uno nuevo.', 'en' => 'For security reasons, this link is only valid for {minutes} minutes. After that, you will need to request a new one.', 'gl' => 'Por motivos de seguridade, esta ligazón só é válida durante {minutes} minutos. Pasado ese tempo, terás que solicitar unha nova.', 'eu' => 'Segurtasun arrazoiengatik, esteka hau {minutes} minutuz baino ez da baliozkoa. Denbora hori igarotakoan, berri bat eskatu beharko duzu.'],
        'password_reset_ignore' => ['ca' => 'Si no has demanat aquest canvi, pots ignorar aquest correu — la teva contrasenya actual seguirà sent vàlida.', 'es' => 'Si no has solicitado este cambio, puedes ignorar este correo: tu contraseña actual seguirá siendo válida.', 'en' => "If you did not request this change, you can ignore this email — your current password will remain valid.", 'gl' => 'Se non solicitaches este cambio, podes ignorar este correo: o teu contrasinal actual seguirá sendo válido.', 'eu' => 'Aldaketa hau ez baduzu eskatu, mezu hau alde batera utz dezakezu — zure uneko pasahitzak baliozkoa izaten jarraituko du.'],
        'password_reset_fallback' => ['ca' => 'Si el botó no funciona, copia i enganxa aquest enllaç al navegador:', 'es' => 'Si el botón no funciona, copia y pega este enlace en el navegador:', 'en' => "If the button doesn't work, copy and paste this link into your browser:", 'gl' => 'Se o botón non funciona, copia e pega esta ligazón no navegador:', 'eu' => 'Botoiak funtzionatzen ez badu, kopiatu eta itsatsi esteka hau arakatzailean:'],

        // ---------------- factura de la quota EvoPro ----------------
        'invoice_subject' => ['ca' => 'La teva factura {number}', 'es' => 'Tu factura {number}', 'en' => 'Your invoice {number}', 'gl' => 'A túa factura {number}', 'eu' => 'Zure {number} faktura'],
        'invoice_body' => ['ca' => "Hem rebut el pagament de la teva quota: {concept}, per un total de {total} (IVA inclòs). La factura {number} ja està disponible a NutriEvo.", 'es' => 'Hemos recibido el pago de tu cuota: {concept}, por un total de {total} (IVA incluido). La factura {number} ya está disponible en NutriEvo.', 'en' => 'We have received the payment for your fee: {concept}, for a total of {total} (VAT included). Invoice {number} is now available in NutriEvo.', 'gl' => 'Recibimos o pagamento da túa cota: {concept}, por un total de {total} (IVE incluído). A factura {number} xa está dispoñible en NutriEvo.', 'eu' => 'Zure kuotaren ordainketa jaso dugu: {concept}, guztira {total} (BEZ barne). {number} faktura eskuragarri dago NutriEvon.'],
        'invoice_button' => ['ca' => 'Veure la factura', 'es' => 'Ver la factura', 'en' => 'View invoice', 'gl' => 'Ver a factura', 'eu' => 'Ikusi faktura'],

        // ---------------- llicència EvoPro a punt de caducar ----------------
        'license_expiring_subject' => ['ca' => 'La teva llicència EvoPro caduca aviat', 'es' => 'Tu licencia EvoPro caduca pronto', 'en' => 'Your EvoPro license expires soon', 'gl' => 'A túa licenza EvoPro caduca en breve', 'eu' => 'Zure EvoPro lizentzia laster iraungiko da'],
        'license_expiring_body' => ['ca' => "La teva llicència EvoPro caduca el {date} (d'aquí {days} dies). Si no la renoves, el teu compte passarà al pla EvoDemo, amb límits de pacients i rutines.", 'es' => 'Tu licencia EvoPro caduca el {date} (en {days} días). Si no la renuevas, tu cuenta pasará al plan EvoDemo, con límites de pacientes y rutinas.', 'en' => 'Your EvoPro license expires on {date} ({days} days left). If you do not renew it, your account will move to the EvoDemo plan, with limits on patients and routines.', 'gl' => 'A túa licenza EvoPro caduca o {date} (en {days} días). Se non a renovas, a túa conta pasará ao plan EvoDemo, con límites de pacientes e rutinas.', 'eu' => 'Zure EvoPro lizentzia {date}(e)an iraungiko da ({days} egun barru). Berritzen ez baduzu, zure kontua EvoDemo planera pasatuko da, pazienteen eta errutinen mugekin.'],
        'license_expiring_body_today' => ['ca' => 'La teva llicència EvoPro caduca avui ({date}). Si no la renoves, el teu compte passarà al pla EvoDemo, amb límits de pacients i rutines.', 'es' => 'Tu licencia EvoPro caduca hoy ({date}). Si no la renuevas, tu cuenta pasará al plan EvoDemo, con límites de pacientes y rutinas.', 'en' => 'Your EvoPro license expires today ({date}). If you do not renew it, your account will move to the EvoDemo plan, with limits on patients and routines.', 'gl' => 'A túa licenza EvoPro caduca hoxe ({date}). Se non a renovas, a túa conta pasará ao plan EvoDemo, con límites de pacientes e rutinas.', 'eu' => 'Zure EvoPro lizentzia gaur iraungiko da ({date}). Berritzen ez baduzu, zure kontua EvoDemo planera pasatuko da, pazienteen eta errutinen mugekin.'],
        'license_expiring_body_tomorrow' => ['ca' => 'La teva llicència EvoPro caduca demà ({date}). Si no la renoves, el teu compte passarà al pla EvoDemo, amb límits de pacients i rutines.', 'es' => 'Tu licencia EvoPro caduca mañana ({date}). Si no la renuevas, tu cuenta pasará al plan EvoDemo, con límites de pacientes y rutinas.', 'en' => 'Your EvoPro license expires tomorrow ({date}). If you do not renew it, your account will move to the EvoDemo plan, with limits on patients and routines.', 'gl' => 'A túa licenza EvoPro caduca mañá ({date}). Se non a renovas, a túa conta pasará ao plan EvoDemo, con límites de pacientes e rutinas.', 'eu' => 'Zure EvoPro lizentzia bihar iraungiko da ({date}). Berritzen ez baduzu, zure kontua EvoDemo planera pasatuko da, pazienteen eta errutinen mugekin.'],
        'license_expiring_button' => ['ca' => 'Veure el meu pla', 'es' => 'Ver mi plan', 'en' => 'View my plan', 'gl' => 'Ver o meu plan', 'eu' => 'Ikusi nire plana'],

        // ---------------- missatge nou ----------------
        'new_message_subject' => ['ca' => 'Tens un missatge nou', 'es' => 'Tienes un mensaje nuevo', 'en' => 'You have a new message', 'gl' => 'Tes unha mensaxe nova', 'eu' => 'Mezu berri bat duzu'],
        'new_message_body' => ['ca' => "{sender} t'ha enviat un missatge nou. Per seguretat no l'incloem en aquest correu: entra a NutriEvo per llegir-lo.", 'es' => '{sender} te ha enviado un mensaje nuevo. Por seguridad no lo incluimos en este correo: entra en NutriEvo para leerlo.', 'en' => "{sender} sent you a new message. For your privacy we don't include it in this email: open NutriEvo to read it.", 'gl' => '{sender} envioute unha mensaxe nova. Por seguridade non a incluímos neste correo: entra en NutriEvo para lela.', 'eu' => '{sender}-(e)k mezu berri bat bidali dizu. Segurtasunagatik ez dugu mezu honetan sartzen: ireki NutriEvo irakurtzeko.'],
        'new_message_button' => ['ca' => 'Llegir el missatge', 'es' => 'Leer el mensaje', 'en' => 'Read the message', 'gl' => 'Ler a mensaxe', 'eu' => 'Irakurri mezua'],
        'disable_hint_messages' => ['ca' => "Pots desactivar aquests avisos a Configuració › Notificacions.", 'es' => 'Puedes desactivar estos avisos en Configuración › Notificaciones.', 'en' => 'You can turn off these emails in Settings › Notifications.', 'gl' => 'Podes desactivar estes avisos en Configuración › Notificacións.', 'eu' => 'Abisu hauek desaktiba ditzakezu Ezarpenak › Jakinarazpenak atalean.'],

        // ---------------- push genèric (fallback per correu) ----------------
        'disable_hint_push_fallback' => ['ca' => "Reps aquest correu perquè no tens les notificacions push actives en cap dispositiu. Pots desactivar-lo a Configuració › Notificacions.", 'es' => 'Recibes este correo porque no tienes las notificaciones push activas en ningún dispositivo. Puedes desactivarlo en Configuración › Notificaciones.', 'en' => 'You are receiving this email because you have no device with push notifications enabled. You can turn it off in Settings › Notifications.', 'gl' => 'Recibes este correo porque non tes as notificacións push activas en ningún dispositivo. Podes desactivalo en Configuración › Notificacións.', 'eu' => 'Mezu hau jasotzen ari zara ez duzulako push jakinarazpenik gaituta gailu batean ere. Ezarpenak › Jakinarazpenak atalean desaktiba dezakezu.'],
        'open_app_button' => ['ca' => 'Obrir NutriEvo', 'es' => 'Abrir NutriEvo', 'en' => 'Open NutriEvo', 'gl' => 'Abrir NutriEvo', 'eu' => 'Ireki NutriEvo'],

        // ---------------- push: alertes urgents ----------------
        'urgent_alert_nutri_one' => ['ca' => 'Tens una alerta urgent nova.', 'es' => 'Tienes una alerta urgente nueva.', 'en' => 'You have a new urgent alert.', 'gl' => 'Tes unha alerta urxente nova.', 'eu' => 'Alerta larri berri bat duzu.'],
        'urgent_alert_nutri_many' => ['ca' => 'Tens {n} alertes urgents noves.', 'es' => 'Tienes {n} alertas urgentes nuevas.', 'en' => 'You have {n} new urgent alerts.', 'gl' => 'Tes {n} alertas urxentes novas.', 'eu' => '{n} alerta larri berri dituzu.'],
        'urgent_alert_patient_one' => ['ca' => 'Tens un avís urgent nou.', 'es' => 'Tienes un aviso urgente nuevo.', 'en' => 'You have a new urgent notice.', 'gl' => 'Tes un aviso urxente novo.', 'eu' => 'Ohartarazpen larri berri bat duzu.'],
        'urgent_alert_patient_many' => ['ca' => 'Tens {n} avisos urgents nous.', 'es' => 'Tienes {n} avisos urgentes nuevos.', 'en' => 'You have {n} new urgent notices.', 'gl' => 'Tes {n} avisos urxentes novos.', 'eu' => '{n} ohartarazpen larri berri dituzu.'],

        // ---------------- push: consells ----------------
        'advice_new_one' => ['ca' => 'Tens un consell nou a NutriEvo.', 'es' => 'Tienes un consejo nuevo en NutriEvo.', 'en' => 'You have a new tip in NutriEvo.', 'gl' => 'Tes un consello novo en NutriEvo.', 'eu' => 'Aholku berri bat duzu NutriEvon.'],
        'advice_new_many' => ['ca' => 'Tens {n} consells nous a NutriEvo.', 'es' => 'Tienes {n} consejos nuevos en NutriEvo.', 'en' => 'You have {n} new tips in NutriEvo.', 'gl' => 'Tes {n} consellos novos en NutriEvo.', 'eu' => '{n} aholku berri dituzu NutriEvon.'],

        // ---------------- push: recordatoris de registre ----------------
        'reminder_morning' => ['ca' => 'Bon dia! Recorda registrar avui a NutriEvo.', 'es' => '¡Buenos días! Recuerda registrar hoy en NutriEvo.', 'en' => 'Good morning! Remember to log today in NutriEvo.', 'gl' => 'Bo día! Lembra rexistrar hoxe en NutriEvo.', 'eu' => 'Egun on! Gogoratu gaur NutriEvon erregistratzea.'],
        'reminder_evening' => ['ca' => "Encara no has registrat avui. Fes-ho abans d'anar a dormir.", 'es' => 'Todavía no has registrado hoy. Hazlo antes de ir a dormir.', 'en' => "You haven't logged anything today yet. Do it before going to sleep.", 'gl' => 'Aínda non rexistraches hoxe. Faino antes de ir durmir.', 'eu' => 'Oraindik ez duzu gaur erregistratu. Egin lo egin aurretik.'],

        // ---------------- alertes: text pel pacient (App\Support\AlertTexts) ----------------
        'alert_title_situation' => ['ca' => 'Hem detectat una situació a vigilar', 'es' => 'Hemos detectado una situación a vigilar', 'en' => "We've noticed something worth watching", 'gl' => 'Detectamos unha situación a vixiar', 'eu' => 'Kontuan hartzeko egoera bat detektatu dugu'],
        'alert_title_low' => ['ca' => 'Hem detectat un valor baix', 'es' => 'Hemos detectado un valor bajo', 'en' => "We've noticed a low value", 'gl' => 'Detectamos un valor baixo', 'eu' => 'Balio baxu bat detektatu dugu'],
        'alert_title_high' => ['ca' => 'Hem detectat un valor elevat', 'es' => 'Hemos detectado un valor elevado', 'en' => "We've noticed a high value", 'gl' => 'Detectamos un valor elevado', 'eu' => 'Balio altu bat detektatu dugu'],
        'alert_today' => ['ca' => 'Avui', 'es' => 'Hoy', 'en' => 'Today', 'gl' => 'Hoxe', 'eu' => 'Gaur'],
        'alert_on_date' => ['ca' => 'El {date}', 'es' => 'El {date}', 'en' => 'On {date}', 'gl' => 'O {date}', 'eu' => '{date}(e)an'],
        'alert_body_alarm' => ['ca' => 'has respost «{value}» a «{label}».', 'es' => 'respondiste «{value}» a «{label}».', 'en' => 'you answered "{value}" to "{label}".', 'gl' => 'respondiches «{value}» a «{label}».', 'eu' => '«{value}» erantzun zenuen «{label}» galderan.'],
        'alert_body_range' => ['ca' => 'has registrat {value} en {label}.', 'es' => 'registraste {value} en {label}.', 'en' => 'you logged {value} for {label}.', 'gl' => 'rexistraches {value} en {label}.', 'eu' => '{value} erregistratu zenuen {label}-(e)n.'],
        'alert_advice_alarm' => ['ca' => 'Si et passa sovint o empitjora, comenta-ho amb el teu nutricionista.', 'es' => 'Si te pasa a menudo o empeora, coméntalo con tu nutricionista.', 'en' => 'If this happens often or gets worse, talk to your nutritionist about it.', 'gl' => 'Se che pasa a miúdo ou empeora, coméntallo ao teu nutricionista.', 'eu' => 'Sarritan gertatzen bazaizu edo okertzen bada, hitz egin zure nutrizionistarekin.'],
        'alert_advice_range' => ['ca' => 'Si aquest valor es manté o empitjora, comenta-ho amb el teu nutricionista.', 'es' => 'Si este valor se mantiene o empeora, coméntalo con tu nutricionista.', 'en' => 'If this value stays the same or gets worse, talk to your nutritionist about it.', 'gl' => 'Se este valor se mantén ou empeora, coméntallo ao teu nutricionista.', 'eu' => 'Balio hori mantentzen bada edo okertzen bada, hitz egin zure nutrizionistarekin.'],
        'alert_advice_urgent_suffix' => ['ca' => " Si et trobes malament, contacta amb el teu equip sanitari; en cas d'urgència, truca al 112.", 'es' => ' Si te encuentras mal, contacta con tu equipo sanitario; en caso de urgencia, llama al 112.', 'en' => ' If you feel unwell, contact your healthcare team; in an emergency, call 112.', 'gl' => ' Se te atopas mal, contacta co teu equipo sanitario; en caso de urxencia, chama ao 112.', 'eu' => ' Gaizki sentitzen bazara, jarri harremanetan zure osasun-taldearekin; larrialdirik izanez gero, deitu 112ra.'],
        'alert_title_urgent' => ['ca' => 'Incidència urgent', 'es' => 'Incidencia urgente', 'en' => 'Urgent issue', 'gl' => 'Incidencia urxente', 'eu' => 'Gorabehera larria'],
        'alert_title_review' => ['ca' => 'Possible incidència', 'es' => 'Posible incidencia', 'en' => 'Possible issue', 'gl' => 'Posible incidencia', 'eu' => 'Balizko gorabehera'],
        // TREND_WORSE i MISSING_DAYS (30/09/2026): proposta IA, pendent de validar (docs/pendent-validacio-nutricionista.xlsx).
        'alert_title_trend_worse' => ['ca' => 'Tendència a vigilar', 'es' => 'Tendencia a vigilar', 'en' => 'Trend worth watching', 'gl' => 'Tendencia a vixiar', 'eu' => 'Kontuan hartzeko joera'],
        'alert_title_trend_worse_nutri' => ['ca' => 'Tendència desfavorable', 'es' => 'Tendencia desfavorable', 'en' => 'Unfavorable trend', 'gl' => 'Tendencia desfavorable', 'eu' => 'Joera desegokia'],
        'alert_body_trend_worse' => ['ca' => "la tendència d'aquesta setmana en {label} no és la que voldríem.", 'es' => 'la tendencia de esta semana en {label} no es la que nos gustaría.', 'en' => "this week's trend in {label} isn't the one we'd like to see.", 'gl' => 'a tendencia desta semana en {label} non é a que quixeramos.', 'eu' => '{label}(r)en aste honetako joera ez da nahiko genukeena.'],
        'alert_advice_trend_worse' => ['ca' => 'Comenta-ho amb el teu nutricionista a la propera visita.', 'es' => 'Coméntalo con tu nutricionista en la próxima visita.', 'en' => 'Mention it to your nutritionist at your next visit.', 'gl' => 'Coméntallo ao teu nutricionista na próxima visita.', 'eu' => 'Aipatu zure nutrizionistari hurrengo bisitan.'],
        'alert_title_missing_days' => ['ca' => 'Fa dies que no registres', 'es' => 'Hace días que no registras', 'en' => "It's been a few days", 'gl' => 'Fai días que non rexistras', 'eu' => 'Egun batzuk daramatzazu erregistratu gabe'],
        'alert_title_missing_days_nutri' => ['ca' => 'Dies sense registrar', 'es' => 'Días sin registrar', 'en' => 'Days without logging', 'gl' => 'Días sen rexistrar', 'eu' => 'Erregistratu gabeko egunak'],
        'alert_body_missing_days' => ['ca' => 'Fa {n} dies que no registres {routine}.', 'es' => 'Hace {n} días que no registras {routine}.', 'en' => "It's been {n} days since you logged anything for {routine}.", 'gl' => 'Fai {n} días que non rexistras {routine}.', 'eu' => '{n} egun daramatzazu {routine} erregistratu gabe.'],
        'alert_advice_missing_days' => ['ca' => "Torna-hi quan puguis, t'ajuda a seguir el teu progrés.", 'es' => 'Vuelve a registrar cuando puedas, te ayuda a seguir tu progreso.', 'en' => "Log in again when you can — it helps track your progress.", 'gl' => 'Volve rexistrar cando poidas, axúdache a seguir o teu progreso.', 'eu' => 'Erregistratu berriro ahal duzunean, zure aurrerapena jarraitzen laguntzen du.'],
    ];

    /**
     * @param  array<string,string>  $replace  Substitucions {clau} → valor dins del text (mai HTML-escapades: fer-ho
     *                                           a la vista si cal).
     */
    public static function t(string $key, ?string $language, array $replace = []): string
    {
        $language = self::normalize($language);
        $text = self::STRINGS[$key][$language] ?? self::STRINGS[$key]['ca'] ?? $key;
        foreach ($replace as $k => $v) {
            $text = str_replace('{'.$k.'}', (string) $v, $text);
        }

        return $text;
    }

    // Un dels 5 idiomes seleccionables (frontend/src/lib/i18n.tsx: LANGUAGES); 'ca' si no és cap d'aquests.
    public static function normalize(?string $language): string
    {
        return in_array($language, ['ca', 'es', 'en', 'gl', 'eu'], true) ? $language : 'ca';
    }

    // Logotip complet (icona + "NutriEvo" + eslògan) en l'idioma, servit com a PNG estàtic des del frontend
    // (frontend/public/branding/email-logo-{idioma}.png) perquè els clients de correu el puguin carregar.
    public static function logoUrl(?string $language): string
    {
        return rtrim(config('app.frontend_url'), '/').'/branding/email-logo-'.self::normalize($language).'.png';
    }
}
