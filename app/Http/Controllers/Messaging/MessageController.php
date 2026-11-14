<?php

namespace App\Http\Controllers\Messaging;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreConversationMessageRequest;
use App\Http\Requests\Messaging\UpdateConversationMessageRequest;
use App\Models\ApplicationConversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\JobApplication;
use App\Models\User;
use App\Policies\ConversationPolicy;
use App\Services\Messaging\ConversationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MessageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Shared inbox for candidates and employers. The query itself is the
     * authorization boundary — only conversations the viewer may participate
     * in are returned.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $allowedCompanyIds = $this->conversations->allowedCompanyIdsFor($user);

        $select = [
            'c.id',
            'c.application_id',
            'c.last_message_id',
            'c.last_message_at',
            'c.created_at',
            'c.sort_at',
            'a.status as application_status',
            'candidate.id as candidate_user_id',
            'candidate.name as candidate_name',
            'j.id as job_id',
            'j.title as job_title',
            'co.id as company_id',
            'co.name as company_name',
            'co.slug as company_slug',
            'co.logo_path as company_logo_path',
        ];

        $makeBranch = fn () => DB::table('application_conversations as c')
            ->join('job_applications as a', 'a.id', '=', 'c.application_id')
            ->join('job_postings as j', 'j.id', '=', 'a.job_posting_id')
            ->join('companies as co', 'co.id', '=', 'j.company_id')
            ->join('users as candidate', 'candidate.id', '=', 'a.user_id')
            ->select($select);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        $candidate = $makeBranch()->where('a.user_id', $user->id);

        if ($allowedCompanyIds === []) {
            $union = $candidate;
            $total = (clone $candidate)->count();
        } else {
            $employer = $makeBranch()->whereIn('j.company_id', $allowedCompanyIds);
            $union = $candidate->unionAll($employer);
            $total = (clone $candidate)->count() + (clone $employer)->count();
        }

        $rows = DB::query()
            ->fromSub($union, 'inbox')
            ->orderByDesc('sort_at')
            ->forPage($page, $perPage)
            ->get()
            ->all();

        $pageIds = array_map(fn (object $row): int => (int) $row->id, $rows);

        $cursors = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $pageIds)
            ->pluck('last_read_message_id', 'conversation_id');

        $latestMessages = $this->latestMessagesFor($pageIds);

        $items = collect($rows)->map(function (object $row) use ($user, $cursors, $latestMessages): array {
            $row = (array) $row;
            $id = (int) $row['id'];
            $latest = $latestMessages[$id] ?? null;
            $isCandidate = (int) $row['candidate_user_id'] === (int) $user->id;
            $cursor = (int) ($cursors[$id] ?? 0);
            $lastId = (int) ($row['last_message_id'] ?? 0);
            $lastMessage = $latest === null ? null : [
                'body' => $latest->body,
                'sender_id' => $latest->sender_id,
                'sender_name' => $latest->sender->name,
                'created_at' => $latest->created_at?->toIso8601String(),
            ];

            return [
                'id' => $id,
                'viewer_role' => $isCandidate ? 'candidate' : 'employer',
                'application_id' => (int) $row['application_id'],
                'application_status' => (string) $row['application_status'],
                'job' => [
                    'id' => (int) $row['job_id'],
                    'title' => (string) $row['job_title'],
                ],
                'company' => [
                    'id' => (int) $row['company_id'],
                    'name' => (string) $row['company_name'],
                    'slug' => (string) $row['company_slug'],
                    'logo_path' => $row['company_logo_path'],
                ],
                'other_party_name' => $isCandidate
                    ? (string) $row['company_name']
                    : (string) $row['candidate_name'],
                'unread' => $lastId > $cursor,
                'last_message' => $lastMessage,
                'last_message_at' => $row['last_message_at'],
                'created_at' => $row['created_at'],
            ];
        })->values()->all();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return Inertia::render('messages/index', [
            'conversations' => [
                'data' => $items,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'prev_page_url' => $page > 1 ? $request->fullUrlWithQuery(['page' => $page - 1]) : null,
                'next_page_url' => $page < $lastPage ? $request->fullUrlWithQuery(['page' => $page + 1]) : null,
            ],
            'unread_total' => $this->conversations->unreadCountFor($user),
        ]);
    }

    /**
     * Latest non-deleted message per conversation id, in one bounded query.
     *
     * @param  array<int, int>  $pageIds
     * @return array<int, ConversationMessage>
     */
    private function latestMessagesFor(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        $rows = ConversationMessage::withTrashed()
            ->select([
                'conversation_messages.conversation_id',
                'conversation_messages.id',
                'conversation_messages.sender_id',
                'conversation_messages.body',
                'conversation_messages.created_at',
                'conversation_messages.deleted_at',
            ])
            ->joinSub(
                DB::table('conversation_messages')
                    ->selectRaw('MAX(id) as max_id, conversation_id')
                    ->whereIn('conversation_id', $pageIds)
                    ->whereNull('deleted_at')
                    ->groupBy('conversation_id'),
                'latest',
                'latest.max_id',
                '=',
                'conversation_messages.id'
            )
            ->with('sender:id,name')
            ->get();

        return $rows->keyBy('conversation_id')->all();
    }

    /**
     * Thread page. Marks the conversation as read for the current viewer.
     */
    public function show(Request $request, ApplicationConversation $conversation): InertiaResponse
    {
        $this->authorize('view', $conversation);

        /** @var User $user */
        $user = $request->user();

        $conversation->loadMissing([
            'application.jobPosting.company:id,name,slug,logo_path,owner_user_id',
            'application.user:id,name',
        ]);

        $this->conversations->markRead($conversation, $user);

        $application = $conversation->application;
        $job = $application->jobPosting;
        $company = $job->company;
        $isCandidate = (int) $application->user_id === (int) $user->id;

        $messages = $this->threadMessages($conversation, $request, $user);

        $canSend = Gate::allows('send', $conversation) && ! $application->status->isTerminal();

        return Inertia::render('messages/show', [
            'conversation' => [
                'id' => $conversation->id,
                'viewer_role' => $isCandidate ? 'candidate' : 'employer',
                'application_id' => $application->id,
                'application_status' => $application->status->value,
                'application_status_label' => $application->status->label(),
                'job' => [
                    'id' => $job->id,
                    'title' => $job->title,
                ],
                'company' => $company === null ? null : [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'logo_path' => $company->logo_path,
                ],
                'other_party_name' => $isCandidate
                    ? $company->name
                    : $application->user->name,
                'can_send' => $canSend,
                'read_only_notice' => $application->status->isTerminal()
                    ? __('This application is :status — messaging is read-only.', ['status' => $application->status->label()])
                    : null,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message. Broadcast + notification happen after the transaction commits.
     */
    public function store(StoreConversationMessageRequest $request, ApplicationConversation $conversation): RedirectResponse
    {
        $this->authorize('send', $conversation);

        /** @var User $user */
        $user = $request->user();

        $message = $this->conversations->send($conversation, $user, $request->validated('body'));

        broadcast(new MessageSent($message));
        $this->notificationService->newConversationMessage($message);

        return back()->with('success', __('Message sent.'));
    }

    /**
     * Advance the viewer's read cursor (called by the client when a live message
     * arrives while the thread is open). Idempotent.
     */
    public function read(Request $request, ApplicationConversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        /** @var User $user */
        $user = $request->user();

        $this->conversations->markRead($conversation, $user);

        return response()->noContent();
    }

    /**
     * Open-or-create the conversation for an application, then redirect to it.
     */
    public function open(Request $request, JobApplication $application): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $application->loadMissing(['jobPosting.company:id,name,slug,owner_user_id']);

        abort_unless(app(ConversationPolicy::class)->viewApplication($user, $application), 403);

        $conversation = $this->conversations->openForApplication($application);

        return redirect()->route('messages.show', ['conversation' => $conversation->id]);
    }

    /**
     * Edit the sender's own message. Broadcast happens after the transaction commits.
     */
    public function update(UpdateConversationMessageRequest $request, ConversationMessage $message): RedirectResponse
    {
        $this->authorize('edit', $message);

        $updated = $this->conversations->update($message, $request->validated('body'));

        broadcast(new MessageEdited($updated));

        return back()->with('success', __('Message updated.'));
    }

    /**
     * Delete (tombstone) the sender's own message. Broadcast happens after the
     * transaction commits.
     */
    public function destroy(ConversationMessage $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $deleted = $this->conversations->delete($message);

        broadcast(new MessageDeleted($deleted));

        return back()->with('success', __('Message deleted.'));
    }

    /**
     * @return array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string|null, edited_at: string|null, deleted_at: string|null, created_at: string|null, is_mine: bool}
     */
    /**
     * Thread history as a keyset (cursor) window — O(page-size) at any depth,
     * unlike offset pagination. Returns a PaginatedData-shaped array of at most
     * 30 chronological items plus a `next_page_url` pointing at the next
     * older window (null when the conversation is fully loaded).
     *
     * @return array{data: array<int, array<string, mixed>>, current_page: int, last_page: int, per_page: int, total: int, prev_page_url: string|null, next_page_url: string|null}
     */
    private function threadMessages(ApplicationConversation $conversation, Request $request, User $viewer): array
    {
        $before = (int) $request->query('before', 0);
        $perPage = 30;

        $query = ConversationMessage::withTrashed()
            ->where('conversation_id', $conversation->id)
            ->select(['id', 'conversation_id', 'sender_id', 'body', 'edited_at', 'deleted_at', 'created_at'])
            ->with('sender:id,name')
            ->orderByDesc('id');

        if ($before > 0) {
            $query->where('id', '<', $before);
        }

        $batch = $query->limit($perPage + 1)->get();
        $hasMore = $batch->count() > $perPage;
        $page = $batch->take($perPage)->sortBy('id')->values();

        $nextPageUrl = $hasMore
            ? route('messages.show', ['conversation' => $conversation->id, 'before' => (int) $page->first()?->id])
            : null;

        return [
            'data' => $page->map(fn (ConversationMessage $message): array => $this->messagePayload($message, $viewer))->values()->all(),
            'current_page' => 1,
            'last_page' => $hasMore ? 2 : 1,
            'per_page' => $perPage,
            'total' => ConversationMessage::withTrashed()->where('conversation_id', $conversation->id)->count(),
            'prev_page_url' => null,
            'next_page_url' => $nextPageUrl,
        ];
    }

    /**
     * @return array{id: int, conversation_id: int, sender_id: int, sender_name: string, body: string|null, edited_at: string|null, deleted_at: string|null, created_at: string|null, is_mine: bool}
     */
    private function messagePayload(ConversationMessage $message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->name,
            'body' => $message->body,
            'edited_at' => $message->edited_at?->toIso8601String(),
            'deleted_at' => $message->deleted_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
            'is_mine' => (int) $message->sender_id === (int) $viewer->id,
        ];
    }
}
