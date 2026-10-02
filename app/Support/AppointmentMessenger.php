<?php

namespace App\Support;

use App\Mail\NewMessageMail;
use App\Models\Appointment;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\NutricionistaProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

// Missatges automàtics del flux de visites (sol·licitud, confirmació, rebuig i anul·lació) i format de dates en català.
// Tots els missatges d'una visita van al mateix fil (`reg_appointments.threadId`), així el nutricionista i el pacient
// en veuen la conversa sencera.
// IMPORTANT: les hores de les visites es desen com a HORA DE RELLOTGE (sense zona horària): el calendari del
// nutricionista envia "2026-10-08 10:00:00" i es guarda tal qual (la BD és UTC, però el valor és "les 10:00" i prou).
// Per això aquí NO es converteix cap zona horària en formatar, i "ara" es compara amb l'hora de rellotge de Madrid
// (`nowWall()`), no amb l'UTC real.
class AppointmentMessenger
{
    public const TZ = 'Europe/Madrid';

    private const WEEKDAYS = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];

    private const MONTHS = ['gener', 'febrer', 'març', 'abril', 'maig', 'juny', 'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre'];

    // "Ara" com a hora de rellotge de Madrid, expressada com els valors desats a la BD (vegeu la nota de dalt).
    public static function nowWall(): Carbon
    {
        return Carbon::parse(Carbon::now(self::TZ)->format('Y-m-d H:i:s'), 'UTC');
    }

    // "dissabte, 26 de setembre" / "dimecres, 14 d'octubre"
    public static function dateText(Carbon $date): string
    {
        $d = $date->copy();
        $month = self::MONTHS[$d->month - 1];
        $of = in_array($month[0], ['a', 'e', 'i', 'o', 'u'], true) ? "d'" : 'de ';

        return self::WEEKDAYS[$d->dayOfWeek].', '.$d->day.' '.$of.$month;
    }

    public static function timeText(Carbon $date): string
    {
        return $date->copy()->format('H:i');
    }

    // Adreça de la consulta (la de la visita o, si no n'hi ha, la del perfil del nutricionista).
    public static function locationOf(Appointment $appointment): ?string
    {
        if ($appointment->modality !== 'IN_PERSON') {
            return null;
        }
        if ($appointment->location) {
            return $appointment->location;
        }
        $profile = NutricionistaProfile::where('userId', $appointment->nutricionistaId)->first(['address', 'postalCode', 'city']);
        $line = trim(implode(', ', array_filter([$profile?->address, trim(($profile?->postalCode ?? '').' '.($profile?->city ?? ''))])));

        return $line !== '' ? $line : null;
    }

    // Envia un missatge dins el fil de la visita (el crea si encara no n'hi ha). `$link`: enllaça el missatge a la
    // visita perquè el nutricionista hi vegi els botons Acceptar / Rebutjar (només la sol·licitud inicial).
    public static function send(Appointment $appointment, User $from, User $to, string $subject, string $bodyHtml, bool $link = false): Message
    {
        $thread = $appointment->threadId ? MessageThread::find($appointment->threadId) : null;
        if (! $thread) {
            $thread = MessageThread::create([
                'patientId' => $appointment->patientId,
                'subject' => $subject,
                'lastMessageAt' => now(),
            ]);
            $appointment->forceFill(['threadId' => $thread->id])->save();
        }

        $message = Message::create([
            'threadId' => $thread->id,
            'senderId' => $from->id,
            'recipientId' => $to->id,
            'body' => MessageHtml::sanitize($bodyHtml),
            'appointmentId' => $link ? $appointment->id : null,
        ]);
        $thread->update(['lastMessageAt' => now()]);

        self::notify($to, $from, $thread);

        return $message;
    }

    // Mateix avís genèric per correu (sense contingut) que la resta de missatges, si el destinatari l'ha activat.
    private static function notify(User $recipient, User $sender, MessageThread $thread): void
    {
        if (! $recipient->notifyMessagesByEmail || $recipient->deletedAt) {
            return;
        }
        try {
            Mail::to($recipient->email)->send(new NewMessageMail(
                $recipient->name,
                $sender->name,
                rtrim((string) config('app.frontend_url'), '/').'/messages/'.$thread->id,
                $recipient->language,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // Visita tal com la veu el pacient (API).
    public static function presentForPatient(Appointment $appointment): array
    {
        $nutri = $appointment->nutricionista;
        $profile = $nutri?->nutricionistaProfile;
        $upcoming = in_array($appointment->status, ['REQUESTED', 'CONFIRMED'], true) && $appointment->endAt->gte(self::nowWall());

        return [
            'id' => $appointment->id,
            'startAt' => $appointment->startAt,
            'endAt' => $appointment->endAt,
            'status' => $appointment->status,
            'upcoming' => $upcoming,
            'reason' => $appointment->reason,
            'modality' => $appointment->modality,
            'location' => self::locationOf($appointment),
            'patientNotes' => $appointment->patientNotes,
            'threadId' => $appointment->threadId,
            'nutricionista' => [
                'id' => $nutri?->id,
                'name' => $nutri?->name,
                'subtitle' => $profile?->companyName ?: 'Nutricionista',
                'photoUrl' => UrlHelper::toAbsoluteUrl($profile?->logoUrl),
            ],
        ];
    }

    // Esdeveniment lligat a un missatge, per pintar Acceptar/Rebutjar al nutricionista.
    public static function presentForMessage(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'status' => $appointment->status,
            'startAt' => $appointment->startAt,
            'endAt' => $appointment->endAt,
            'modality' => $appointment->modality,
        ];
    }
}
