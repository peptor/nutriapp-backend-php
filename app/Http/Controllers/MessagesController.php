<?php

namespace App\Http\Controllers;

use App\Mail\NewMessageMail;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Patient;
use App\Models\User;
use App\Support\MessageHtml;
use App\Support\UrlHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Missatgeria interna nutricionista <-> pacient. Un fil pertany a una fila sys_patients
// (que ja identifica les dues parts); cada missatge porta el seu remitent i destinatari
// i el seu propi estat de lectura.
class MessagesController extends Controller
{
    private const ATTACHMENT_RULES = ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'];

    private function isNutri(User $user): bool
    {
        return $user->role === 'NUTRICIONISTA';
    }

    // Fils on participa l'usuari (com a nutricionista o com a pacient).
    private function threadsOf(User $user): Builder
    {
        return MessageThread::whereHas('patient', function (Builder $q) use ($user) {
            $q->where($this->isNutri($user) ? 'nutricionistaId' : 'userId', $user->id);
        });
    }

    private function unreadFor(User $user): \Closure
    {
        return fn (Builder $q) => $q->where('recipientId', $user->id)->whereNull('readAt');
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'count' => $this->threadsOf($user)->whereHas('messages', $this->unreadFor($user))->count(),
        ]);
    }

    // Safata: fils ordenats pel darrer missatge (el més recent primer), en dues pestanyes
    // (amb missatges pendents de llegir / ja llegits) i filtrables per pacient o nutricionista.
    public function index(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'tab' => ['nullable', 'in:unread,read'],
            'patientId' => ['nullable', 'string', 'max:36'],
            'nutricionistaId' => ['nullable', 'string', 'max:36'],
        ]);

        $base = $this->threadsOf($user);
        if ($this->isNutri($user) && $request->filled('patientId')) {
            $base->where('patientId', $request->query('patientId'));
        }
        if (! $this->isNutri($user) && $request->filled('nutricionistaId')) {
            $nutriId = $request->query('nutricionistaId');
            $base->whereHas('patient', fn (Builder $q) => $q->where('nutricionistaId', $nutriId));
        }

        $unread = $this->unreadFor($user);
        $counts = [
            'unread' => (clone $base)->whereHas('messages', $unread)->count(),
            'read' => (clone $base)->whereDoesntHave('messages', $unread)->count(),
        ];

        $tab = $request->query('tab') === 'read' ? 'read' : 'unread';
        $query = $tab === 'unread'
            ? $base->whereHas('messages', $unread)
            : $base->whereDoesntHave('messages', $unread);

        $page = $query
            ->with([
                'patient.user:id,name',
                'patient.nutricionista:id,name',
                'patient.nutricionista.nutricionistaProfile:userId,companyName,logoUrl',
                'latestMessage',
            ])
            ->withCount(['messages as unreadCount' => $unread, 'messages as messageCount'])
            ->orderByDesc('lastMessageAt')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (MessageThread $t) => $this->threadSummary($t, $user))->values(),
            'counts' => $counts,
            'page' => $page->currentPage(),
            'lastPage' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    // Obre un fil: retorna tots els missatges i marca com a llegits els que rep l'usuari.
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $thread = $this->threadsOf($user)->where('id', $id)
            ->with(['patient.user:id,name', 'patient.nutricionista:id,name', 'patient.nutricionista.nutricionistaProfile:userId,companyName,logoUrl'])
            ->first();
        if (! $thread) {
            return response()->json(['error' => 'Missatge no trobat'], 404);
        }

        Message::where('threadId', $thread->id)
            ->where('recipientId', $user->id)
            ->whereNull('readAt')
            ->update(['readAt' => now()]);

        $messages = $thread->messages()->with('sender:id,name')->orderBy('createdAt')->get();

        return response()->json([
            'id' => $thread->id,
            'subject' => $thread->subject,
            'counterpart' => $this->counterpart($thread, $user),
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'isMine' => $m->senderId === $user->id,
                'senderName' => $m->sender->name ?? null,
                'body' => $m->body,
                'createdAt' => $m->createdAt,
                'readAt' => $m->readAt,
                'attachment' => $m->attachmentPath ? [
                    'name' => $m->attachmentName,
                    'mime' => $m->attachmentMime,
                    'size' => $m->attachmentSize,
                ] : null,
            ])->values(),
        ]);
    }

    // Nou fil. El nutricionista indica `patientId` (fila del pacient); el pacient, `nutricionistaId`.
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:100000'],
            'patientId' => ['nullable', 'string', 'max:36'],
            'nutricionistaId' => ['nullable', 'string', 'max:36'],
            'attachment' => self::ATTACHMENT_RULES,
        ]);

        if ($this->isNutri($user)) {
            $patient = Patient::where('id', $data['patientId'] ?? '')->where('nutricionistaId', $user->id)->first();
            $recipientId = $patient?->userId;
        } else {
            $patient = Patient::where('userId', $user->id)->where('nutricionistaId', $data['nutricionistaId'] ?? '')->first();
            $recipientId = $patient?->nutricionistaId;
        }
        if (! $patient) {
            return response()->json(['error' => 'Destinatari no trobat'], 404);
        }

        $body = MessageHtml::sanitize($data['body']);
        if (MessageHtml::toPlainText($body) === '') {
            return response()->json(['error' => 'El missatge no pot ser buit', 'details' => ['body' => ['El missatge no pot ser buit']]], 400);
        }
        $recipient = User::find($recipientId);
        if (! $recipient || $recipient->deletedAt) {
            return response()->json(['error' => 'Aquest destinatari ja no està disponible'], 409);
        }

        $thread = null;
        $message = null;
        DB::transaction(function () use (&$thread, &$message, $patient, $data, $body, $user, $recipientId, $request) {
            $thread = MessageThread::create([
                'patientId' => $patient->id,
                'subject' => trim($data['subject']),
                'lastMessageAt' => now(),
            ]);
            $message = $this->createMessage($thread, $user->id, $recipientId, $body, $request->file('attachment'));
        });

        $this->notify($recipient, $user, $thread);

        return response()->json(['id' => $thread->id, 'messageId' => $message->id], 201);
    }

    public function reply(Request $request, string $id)
    {
        $user = $request->user();
        $data = $request->validate([
            'body' => ['required', 'string', 'max:100000'],
            'attachment' => self::ATTACHMENT_RULES,
        ]);

        $thread = $this->threadsOf($user)->where('id', $id)->with('patient')->first();
        if (! $thread) {
            return response()->json(['error' => 'Missatge no trobat'], 404);
        }

        $body = MessageHtml::sanitize($data['body']);
        if (MessageHtml::toPlainText($body) === '') {
            return response()->json(['error' => 'El missatge no pot ser buit', 'details' => ['body' => ['El missatge no pot ser buit']]], 400);
        }

        $recipientId = $this->isNutri($user) ? $thread->patient->userId : $thread->patient->nutricionistaId;
        $recipient = User::find($recipientId);
        if (! $recipient || $recipient->deletedAt) {
            return response()->json(['error' => 'Aquest destinatari ja no està disponible'], 409);
        }

        $message = null;
        DB::transaction(function () use (&$message, $thread, $user, $recipientId, $body, $request) {
            $message = $this->createMessage($thread, $user->id, $recipientId, $body, $request->file('attachment'));
            $thread->update(['lastMessageAt' => now()]);
        });

        $this->notify($recipient, $user, $thread);

        return response()->json(['id' => $thread->id, 'messageId' => $message->id], 201);
    }

    // Adjunts privats: només els pot descarregar qui envia o rep el missatge.
    public function attachment(Request $request, string $id)
    {
        $user = $request->user();
        $message = Message::where('id', $id)
            ->where(fn (Builder $q) => $q->where('senderId', $user->id)->orWhere('recipientId', $user->id))
            ->first();

        if (! $message || ! $message->attachmentPath || ! Storage::disk('local')->exists($message->attachmentPath)) {
            return response()->json(['error' => 'Adjunt no trobat'], 404);
        }

        return Storage::disk('local')->download($message->attachmentPath, $message->attachmentName ?: 'adjunt');
    }

    private function createMessage(MessageThread $thread, string $senderId, string $recipientId, string $body, ?UploadedFile $file): Message
    {
        $message = Message::create([
            'threadId' => $thread->id,
            'senderId' => $senderId,
            'recipientId' => $recipientId,
            'body' => $body,
        ]);

        if ($file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $path = $file->storeAs("messages/{$message->id}", Str::random(24).'.'.$extension, 'local');
            $message->update([
                'attachmentPath' => $path,
                'attachmentName' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, ''),
                'attachmentMime' => $file->getMimeType(),
                'attachmentSize' => $file->getSize(),
            ]);
        }

        return $message;
    }

    // Avís genèric per correu (sense cap contingut del missatge) només si el pacient l'ha activat.
    private function notify(User $recipient, User $sender, MessageThread $thread): void
    {
        if ($recipient->role !== 'PACIENT' || ! $recipient->notifyMessagesByEmail || $recipient->deletedAt) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new NewMessageMail(
                $recipient->name,
                $sender->name,
                rtrim(config('app.frontend_url'), '/').'/messages/'.$thread->id,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function counterpart(MessageThread $thread, User $user): array
    {
        if ($this->isNutri($user)) {
            return [
                'name' => $thread->patient->user->name ?? null,
                'subtitle' => null,
                'photoUrl' => UrlHelper::toAbsoluteUrl($thread->patient->photoUrl),
                'patientId' => $thread->patientId,
                'nutricionistaId' => null,
            ];
        }

        $nutri = $thread->patient->nutricionista;
        $profile = $nutri?->nutricionistaProfile;

        return [
            'name' => $nutri->name ?? null,
            'subtitle' => $profile?->companyName,
            'photoUrl' => UrlHelper::toAbsoluteUrl($profile?->logoUrl),
            'patientId' => null,
            'nutricionistaId' => $thread->patient->nutricionistaId,
        ];
    }

    private function threadSummary(MessageThread $thread, User $user): array
    {
        $last = $thread->latestMessage;

        return [
            'id' => $thread->id,
            'subject' => $thread->subject,
            'lastMessageAt' => $thread->lastMessageAt,
            'unreadCount' => (int) $thread->unreadCount,
            'messageCount' => (int) $thread->messageCount,
            'preview' => $last ? Str::limit(MessageHtml::toPlainText($last->body), 140) : '',
            'lastFromMe' => $last ? $last->senderId === $user->id : false,
            'hasAttachment' => (bool) $last?->attachmentPath,
            'counterpart' => $this->counterpart($thread, $user),
        ];
    }
}
