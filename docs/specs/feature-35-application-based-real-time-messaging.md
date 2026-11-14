# Feature 35 - Application-based real-time messaging

    status: IMPLEMENTED
    depends-on: Feature 21 (applications), Feature 23 (employer applicant views), Feature 25 (in-app notifications)
    scope-note: Feature 28 (company hiring-team members) is honored here through the existing company-scoped
    permission model; no new platform permission is added.

## 1. Goal / non-goals

### Goal

Let the candidate associated with a job application and the currently authorized employer/company participants
exchange private, persistent text messages about that application — with real-time delivery while connected and
reliable retrieval after reconnecting — through a single application-scoped conversation per application.

### Non-goals (explicitly out of scope)

- File attachments, image uploads, voice messages, audio/video calls.
- Group chats, public chat rooms, typing indicators, presence indicators, message reactions.
- Any paid hosted messaging/WebSocket service or third-party chat API.
- Free-form user-to-user direct messaging unrelated to an application.
- Platform-administrator access to private conversations (no admin panel for messages; moderation happens via the existing report flow, Feature 30).
- Read receipts per message (a per-participant read cursor is stored; no per-message seen state).
- Email delivery of messages (in-app notifications only, via Feature 25).
- Changes to the application status workflow (Feature 24 remains the single source of truth for transitions).
- Backfilling conversations for historical applications (conversations are created lazily, get-or-create).

## 2. User-visible behavior and acceptance criteria

1. One conversation per application. Opening the conversation for an application that has never had one creates it
   atomically; opening it again returns the same conversation. Two concurrent first opens cannot create duplicates.
2. Both parties see a shared **Messages** inbox listing the conversations they participate in (as candidate or as
   employer/company participant), newest activity first, with job title, company name, latest-message preview,
   timestamp, and unread state; the list is server-paginated.
3. The thread page shows paginated message history (newest page first, "Load earlier messages"), sender, and
   timestamps; auto-scrolls to the newest message.
4. Sending a message persists it first (in one transaction), then broadcasts it to private channel
   `conversation.{id}` **after** the transaction commits. Recipients on the thread page receive it without a
   refresh; recipients who are disconnected retrieve it on their next page load.
   4a. A participant can **edit** or **delete** their **own** message. Editing replaces the body and sets
   `edited_at`; deleted messages become tombstones (body destroyed, `deleted_at` set) rendered as "This
   message was deleted" so pagination and cursors stay stable. Both mutations broadcast
   (`message.edited` / `message.deleted`) on the same private channel — connected peers see the change
   without a refresh; disconnected peers see it on their next load. Edited messages show an "(edited)" label.
   4b. Edit and delete are subject to the same participant predicate as sending (revoked employer members lose
   edit/delete rights) and are **blocked on terminal applications** together with sending (§3 A1/A11).
   4c. Editing or deleting a message never creates an in-app notification (anti-noise; Feature 25 covers new
   messages only).
5. The inbox shows an unread indicator per conversation and a global unread badge (count of conversations with
   unread messages) in the app shell while authenticated.
6. Read state is tracked per participant per conversation (a per-user cursor), so unread state is consistent
   across sessions and devices. Viewing a thread advances the viewer's cursor.
7. The recipient gets an in-app notification (Feature 25, database channel) when a message is sent. The
   notification names the sender, job title, and links to the conversation; it **never** contains the message body.
8. Only participants can list, view, send, or read-marker a conversation. Unrelated candidates, other companies'
   employers, and platform administrators cannot view or send, even by guessing IDs or channel names.
9. **Read-only on terminal applications (approved product decision):** once the application reaches a terminal
   status (`hired`, `rejected`, `withdrawn`), the conversation is read-only for **both** sides. History is always
   preserved and remains visible in inbox and thread; the composer is hidden/disabled server-side enforced
   (422 on send), not merely client-side.
10. A closed or archived **job** does not block messaging while the application is non-terminal (an application
    outlives job closure). Only application terminal status gates sending.
11. Conversation history survives application/job closure indefinitely. Messages are soft-deletable by their
    sender only (tombstone; content destroyed); there is no hard purge and no admin moderation route.
12. No new platform permission is introduced. Employer/company access reuses the company-scoped `applicants.view`
    (read) and `applicants.manage` (send/edit/delete) permissions from Feature 28. Reviewers (`applicants.view`
    only) get a read-only experience (no send, no edit/delete of their own — they cannot author messages).
13. Message bodies are plain text; edit/delete mutations are re-authorized server-side on every request and
    broadcast to the private channel only after commit; message bodies never enter notifications, logs, or the
    audit log.

## 3. Assumptions / decisions (labeled)

- **A1 (decided with user):** Terminal-state applications (`hired`, `rejected`, `withdrawn`) are read-only for both
  participants. History preserved. Non-terminal only.
- **A2:** No real-time messaging stack is installed today (`laravel/reverb`, `laravel-echo`, `pusher-js` absent;
  `config/broadcasting.php` absent; `BROADCAST_CONNECTION=log` in `.env.example`, `null` in `phpunit.xml`,
  `database` queue default). The spec adopts **Laravel Reverb** (first-party, self-hosted WebSocket server) as the
  approved-by-overview option and **Laravel Echo + pusher-js** (Reverb speaks the Pusher protocol; no Pusher
  account/service involved) as the frontend listener. Installing these is part of `/develop` and requires approval
  at the gate.
- **A3:** Broadcasts use `ShouldBroadcast` (queued). Local/prod require a running `queue:work` process and the
  Reverb server; this is documented as a dev requirement (same posture as Feature 29's scheduler note). Tests run
  with `QUEUE_CONNECTION=sync` + `BROADCAST_CONNECTION=null` and assert via `Broadcast::fake()`/`Event::fake()`.
  If dependency coupling to the queue worker is undesirable, `ShouldBroadcastNow` is the documented fallback —
  decision deferred to implementation-time review, default stays `ShouldBroadcast`.
- **A4:** Company-scoped participation: employer-side **view** = `applicants.view` (owner via `owner_user_id`, else
  active membership whose role grants it — owner/recruiter/reviewer). Employer-side **send** = `applicants.manage`
  (owner/recruiter). **Reviewer = read-only**. Revoked members lose access immediately (`companyRole()` re-reads
  membership status on every check). Platform admins have no access path.
- **A5:** One shared `/messages` inbox page for both personas; the viewer's side is derived per conversation
  server-side (`candidate` vs `employer`) and from shared auth props for layout chrome. No admin inbox.
- **A6:** Message bodies are plain text, rendered by React's default escaping. No HTML, no markdown rendering, no
  `dangerouslySetInnerHTML`.
- **A7:** Notification coalescing: at most one unread `message.received` notification per conversation per recipient;
  a second message while one is unread updates that notification's `data` (sender/title/timestamp) instead of
  creating a duplicate row (satisfies Feature 25's duplicate-suppression rule). The notification payload carries
  only sender display name, job title, and the conversation URL — never the body. If this is unwanted, it can be
  reverted to per-message notifications at review time without schema changes.
- **A8:** Read cursors live in `conversation_participants` purely as per-user cursors (`last_read_message_id`);
  authorization is **never** derived from this table — it is always re-evaluated from the application/job/company
  and company permission at every request and channel authorization.
- **A9:** Inbox badge totals are computed as "count of conversations with unread messages", not "count of unread
  messages" — O(1) per row via one cursor comparison, no counters to drift.
- **A10:** Existing applications get conversations lazily on first open; there is no data backfill.
- **A11:** Terminal applications (`hired`, `rejected`, `withdrawn`) are fully read-only: send, **edit, and
  delete** are all blocked server-side (422). History remains visible. (Extends A1 to the two new mutations.)
- **A12:** Edit/delete authorization = sender-only **and** the same participant predicate as `send` (candidate:
  application owner; employer side: `applicants.manage` on the job's company). So an employer member whose access
  was revoked (or whose role was demoted) cannot edit/delete even their own previously sent messages. A reviewer
  (view-only) cannot author messages and therefore cannot edit/delete any.
- **A13:** Delete = **tombstone**, not a row removal: `deleted_at` set and `body` set to `NULL` (content
  destroyed server-side). The row persists so `id`-ordered pagination and the `last_read_message_id` cursor stay
  aligned; the UI renders "This message was deleted". Edit sets `edited_at` and replaces `body`. Neither mutation
  generates a notification.

## 4. Routes and access boundary

All under the existing `auth` group. Named routes follow repo conventions. Candidate-specific and employer-
specific middleware stay out of these routes; authorization is Policy-driven (routes only need authentication),
because both personas may open any permitted conversation.

| Method | URI                                        | Name               | Middleware              | Purpose                                                                        |
| ------ | ------------------------------------------ | ------------------ | ----------------------- | ------------------------------------------------------------------------------ |
| GET    | `/messages`                                | `messages.index`   | `auth`                  | Shared inbox (paginated) for candidate + employer viewers                      |
| GET    | `/messages/{conversation}`                 | `messages.show`    | `auth`                  | Thread page (paginated history); marks read for viewer                         |
| POST   | `/messages/{conversation}/messages`        | `messages.store`   | `auth`, `throttle:30,1` | Send message                                                                   |
| POST   | `/messages/{conversation}/read`            | `messages.read`    | `auth`, `throttle:60,1` | Advance read cursor (sent by client when a live message arrives while open)    |
| POST   | `/applications/{application}/conversation` | `messages.open`    | `auth`, `throttle:30,1` | Get-or-create the conversation for an application; redirect to `messages.show` |
| PUT    | `/messages/{message}`                      | `messages.update`  | `auth`, `throttle:30,1` | Edit own message                                                               |
| DELETE | `/messages/{message}`                      | `messages.destroy` | `auth`, `throttle:30,1` | Delete (tombstone) own message                                                 |

Broadcasting auth endpoint `POST /broadcasting/auth` is registered automatically by `bootstrap/app.php`
`withRouting(..., channels: routes/channels.php)` (added in this feature, see §10). Channel callbacks live in
`routes/channels.php` using `Broadcast::channel(...)`.

Private channel: **`conversation.{conversationId}`** — authorized only for a user who (a) is the application's
candidate, or (b) is an approved employer with company permission `applicants.view` for the job's company.
Channel authorization re-evaluates (b) on every subscription auth request (membership status re-read), so revoked
members cannot re-subscribe. HTTP message/list endpoints and channel auth all call the same Policy; possession of
a channel name or a conversation id is never treated as authorization.

Inertia responses: `messages.show` renders `messages/show`; `messages.index` renders `messages/index`.
Application show pages (`candidate/applications/show`, `employer/applications/show`) gain a `can_message` boolean
prop (policy view && application not terminal) driving an "Open conversation" button (POST `messages.open`).

## 5. Policy actions and dynamic role/permission resolution

New policy: `App\Policies\ConversationPolicy` (mirrors `InterviewPolicy`; `JobApplicationPolicy` stays untouched).

- `view(User $actor, ApplicationConversation $conversation): bool`
    - `$actor->is($conversation->application->user)` → true; otherwise the employer predicate below.
- `send(User $actor, ApplicationConversation $conversation): bool`
    - Same participant predicate as `view` **plus** send-permission on the employer side (candidate: no extra
      permission — role `job_seeker` is implied by route group membership... the route group is shared, so re-check
      the candidate branch explicitly: `$actor->is($conversation->application->user)`).
    - Terminal state is **not** part of the Policy; it is enforced in the service with a 422 business error (§7).
- `edit(User $actor, ConversationMessage $message): bool` / `delete(User $actor, ConversationMessage $message): bool`
    - `$actor->is($message->sender)` **and** the `send` participant predicate on the message's conversation
      (A12). The terminal-state block is enforced in the service (422), not in the Policy.
- Employer predicate (private helper): `$actor->isApprovedEmployer() && $company !== null &&
  $actor->hasCompanyPermission($company, 'applicants.view'|'applicants.manage')` where `$company =
  $conversation->application->jobPosting->company`.
- Resolver semantics (unchanged platform machinery): candidate ⇒ row matches `job_applications.user_id`;
  employer ⇒ `isApprovedEmployer()` + company permission via `companyRole()` (owner ⇒ `owner_user_id` match; else
  active `company_members` row; role → permission via `CompanyMemberRole::permissions()`). No platform permission,
  no hard-coded role names outside the existing helpers; navigation visibility stays presentation-only.
- `view-any`/inbox listing deliberately has **no** Policy method — the inbox query itself is the authorization
  boundary (only rows the viewer may see are returned). Documented in code comment.

## 6. Form Requests and validation

Two Form Requests; the other endpoints take no body beyond route bindings.

`App\Http\Requests\Messaging\StoreConversationMessageRequest`

- `body`: required, string, `max:2000`; after `TrimStrings`-style trimming the trimmed value is used and must be
  non-empty (whitespace-only → validation error). No other fields. `authorize()` returns `true` — the controller
  authorizes via `ConversationPolicy::send` (mirrors repo pattern where policies perform authorization).
- Errors map to the `body` field in the Inertia form.

`App\Http\Requests\Messaging\UpdateConversationMessageRequest`

- Identical `body` rules as the store request. `authorize()` returns `true`; the controller authorizes via
  `ConversationPolicy::edit`.

`messages.open` / `messages.read` / `messages.destroy` take no payload (route-model resolution only; throttle
applies). Message edit shares the same 2000-char constant as send; frontend enforces maxlength client-side as UX only.

## 7. Models, relationships, migration, constraints, indexes, deletion semantics

### Tables (new migrations; never edit applied migrations)

**`application_conversations`**

- `id` bigint PK
- `application_id` foreignId → `job_applications.id`, `cascadeOnDelete`, **unique** (enforces one-conversation-per-
  application at the database level; race-safe with insert-catch-retry in the service)
- `last_message_id` unsignedBigInteger **nullable** (denormalized pointer to the newest message; plain column, no
  FK — ids increase monotonically and the column is never queried by value, so no index and no backing FK needed)
- `last_message_at` timestamp **nullable** (denormalized, for inbox ordering)
- timestamps

**`conversation_messages`**

- `id` bigint PK
- `conversation_id` foreignId → `application_conversations.id`, `cascadeOnDelete`
- `sender_id` foreignId → `users.id`, `cascadeOnDelete`
- `body` text **nullable** (plain text; 2000-char limit enforced at request layer; set to `NULL` on soft delete)
- `edited_at` timestamp **nullable** (set on every edit; immutable otherwise)
- `deleted_at` timestamp **nullable** (Laravel SoftDeletes; set on delete)
- timestamps
- Composite index `(conversation_id, id)`: covers FK backing for `conversation_id` (leftmost prefix), message
  pagination by id, and the unread-max checks. Do **not** add a redundant single index on `conversation_id`.
  `sender_id` gets its FK-backing index from the constraint on MySQL; do not add a redundant one.
- Thread/inbox payloads render **tombstones** for soft-deleted messages (body `NULL`; row retained for cursor and
  pagination alignment) — deleted bodies never reach the client.

**`conversation_participants`** (read cursors only — see A8)

- `id` bigint PK
- `conversation_id` foreignId → `application_conversations.id`, `cascadeOnDelete`
- `user_id` foreignId → `users.id`, `cascadeOnDelete`
- `last_read_message_id` unsignedBigInteger **nullable**, FK → `conversation_messages.id` **`nullOnDelete`**
  (if the referenced message were ever deleted, the cursor degrades gracefully to "all unread" instead of blocking)
- `unique(conversation_id, user_id)` (one cursor per participant per conversation; upserted on read)
- Index `user_id` (FK backing + the shared badge aggregate over the viewer's cursor rows)
- Timestamps

All DDL guarded by `Schema::hasTable()`/`Schema::hasIndex()` with idempotent `up()`/`down()` per repo convention
(see Feature 34 migration pattern). `down()` drops the three tables (safe pre-data rollback). Names to be
timestamped by the implementer after the last existing migration (`2026_11_16_000000+`).

### Models

- `App\Models\ApplicationConversation`: `belongsTo JobApplication`; `hasMany ConversationMessage`;
  `hasOne ConversationMessage (latestMessage) ->ofMany('id', 'max')` (SoftDeletes scope applies automatically —
  a deleted newest message falls back to the previous non-deleted one); `hasMany ConversationParticipant`.
  `$fillable = ['application_id', 'last_message_id', 'last_message_at']`; cast `last_message_at` datetime.
- `App\Models\ConversationMessage`: `belongsTo ApplicationConversation`; `belongsTo User (sender)`;
  **uses `SoftDeletes`** (`deleted_at`); `$fillable = ['conversation_id', 'sender_id', 'body']`; casts
  `edited_at`/`deleted_at` datetime. `last_message_id` on the conversation may reference a soft-deleted
  message without issue (ids are monotonic; only the comparison direction matters).
- `App\Models\ConversationParticipant`: `belongsTo ApplicationConversation`; `belongsTo User`; belongsTo
  `ConversationMessage` (lastReadMessage, nullable). `$fillable = [...cursor fields]`.
- JobApplication gains `hasOne ApplicationConversation`.
- SoftDeletes only on `conversation_messages`; conversations/cursors are never soft-deleted (A13). No model
  event hooks needed (unlike Feature 34's skill index, nothing derived must stay in sync).

### Deletion semantics

Application rows are never hard-deleted in this system (jobs use `restrictOnDelete` from `job_applications`).
If an application (or user/company) were ever hard-deleted, FKs cascade to conversations/messages/cursors —
an accepted, documented consequence consistent with existing `job_applications` FK behavior. Closed/archived jobs
and terminal applications never hard-delete messages. User-initiated deletes are sender-only tombstones (A13);
no admin delete path exists.

## 8. Query shape, eager loading, pagination, scale

**Inbox (`messages.index`)** — one paginated query, no N+1, ≤ 3 SQL statements + 1 page-cursor query:

1. Compute allowed company ids in PHP: `owned = companies where owner_user_id = me` + active
   `company_members` for me mapped through `CompanyMemberRole::permissions()` keeping roles granting
   `applicants.view`. Bounded by real membership counts (documented assumption: single-digit per user).
2. `ApplicationConversation` query joining `job_applications` → `job_postings` → `companies`,
   `where(a.user_id = me OR j.company_id IN (allowedIds))`, `select` conversation + application id/status + job id/
   title + company id/name/slug/logo_path, `latestMessage` (`hasOne … ofMany('id','max')` — SoftDeletes global
   scope applies, so a deleted newest message falls back to the previous non-deleted one; select body, sender_id,
   created_at), cursor via one `whereIn('conversation_id', pageIds)->where('user_id', me)->pluck(
   'last_read_message_id', 'conversation_id')` after pagination (page ≤ 15). Order:
   `ORDER BY COALESCE(application_conversations.last_message_at, application_conversations.created_at) DESC`
   (no supporting index; the WHERE side is selective at expected scale — EXPLAIN check required, see §12).
   Per-row unread flag = `COALESCE(cursor, 0) < COALESCE(last_message_id, 0)`.
3. Paginate 15/page (`?page=`), `PaginatedData<ConversationListItem>` flat prop shape, `withQueryString()`.

**Thread (`messages.show`)** — `where('conversation_id', …)` `orderByDesc('id')` (SoftDeletes scoped), `select(
['id','conversation_id','sender_id','body','edited_at','deleted_at','created_at'])`, `paginate(30)` (`?page=`),
items reversed to chronological order for render; `with('sender:id,name')`. Tombstones are included with
`body = NULL` and `deleted_at` set (rendered client-side as "This message was deleted") — never filtered out,
so page boundaries stay stable. Initial unread-marking is done server-side on render
(idempotent cursor upsert — §9).

**Send** — inside `DB::transaction()`:

1. `lockForUpdate()` the `job_applications` row (re-check terminal status under lock → `ValidationException`
   `body` → 422 "Messaging is closed for this application." if terminal);
2. insert message (fill from authenticated sender only);
3. update conversation `last_message_id`/`last_message_at` (same transaction, cannot observe a missing message);
4. upsert the sender's participant cursor to the new message id (keeps sender unread at zero).
   Broadcast + notification happen **after** commit (own code path, outside the transaction) — §9.

**Edit / delete** — single-row updates, same `lockForUpdate()` terminal re-check on the application (422 on
terminal for both mutations, A11); sender-only authorized via `ConversationPolicy::edit|delete` (A12):

- `edit`: set `body` (trimmed, non-empty, ≤ 2000) and `edited_at = now()`.
- `delete`: set `body = NULL` and `deleted_at = now()` (tombstone; content destroyed).
  Broadcast happens **after** each commit — never inside — using the `MessageEdited` / `MessageDeleted` events
  (§9). Neither path creates a notification or touches read cursors (an already-sent message remains "read up to"
  the same cursor regardless of edits/deletes).

**Shared badge** — extend `HandleInertiaRequests::share()` with `messages_unread_count`: count of conversations
with `COALESCE(cursor,0) < COALESCE(last_message_id,0)` for the viewer (candidate branch + employer branch summed;
two bounded indexed aggregates, no row loading). Mirrors the existing `notifications_unread_count` pattern. If it
profiles poorly, the documented opt-out is to compute the badge only on messaging pages.

**Messages per conversation** are bounded by pagination everywhere; inbox page ≤ 15 rows; unread flag is O(1) per
row. No counters, no denormalized totals, no "unread_count" columns anywhere.

## 9. Service/Repository decision and transaction boundaries

**Service, not repository.** `App\Services\Messaging\ConversationService` encapsulates the two multi-step,
transactional workflows:

- `openForApplication(JobApplication $application): ApplicationConversation` — get-or-create; handles the unique
  race (insert, catch `QueryException` for duplicate key, re-select).
- `send(ApplicationConversation $conversation, User $sender, string $body): ConversationMessage` —
  lock-application/terminal check, insert, cursor + conversation pointers in one transaction; throws
  `ValidationException` on terminal state.
- `update(ConversationMessage $message, User $actor, string $body): ConversationMessage` — sender re-authorized
  by controller (Policy) + terminal re-check under lock, `body`/`edited_at` update in one transaction.
- `delete(ConversationMessage $message, User $actor): ConversationMessage` — same lock + terminal re-check;
  soft delete (`body → NULL`, `deleted_at`).
- `markRead(ApplicationConversation $conversation, User $viewer): void` — upsert cursor to `last_message_id`;
  no-op when the conversation has no messages.

Rationale: multi-step transactional writes + cross-aggregate invariants (conversation pointer ↔ newest message;
cursor monotonicity) justify a Service per the AGENTS decision tree. A Repository adds nothing here (no reuse, no
complex aggregate join reused elsewhere) — deliberately omitted. Broadcasting and notification creation live in the
controller (via `ConversationService` for the transactional part, then two fail-soft notifier calls after commit).

**Broadcast events** (all `ShouldBroadcast`, same private channel `conversation.{id}`, constructed and dispatched
**after** the transaction returns — never inside it):

- `App\Events\MessageSent` → `broadcastAs(): 'message.sent'`; `broadcastWith(): ['message' => ['id',
  'conversation_id', 'sender_id', 'sender_name', 'body', 'created_at']]`.
- `App\Events\MessageEdited` → `broadcastAs(): 'message.edited'`; payload `{'message' => ['id',
  'conversation_id', 'body', 'edited_at']}` (no sender needed — recipients match by id).
- `App\Events\MessageDeleted` → `broadcastAs(): 'message.deleted'`; payload `{'message' => ['id',
  'conversation_id', 'deleted_at']}` (body **never** included/re-sent).

All three share `broadcastOn(): PrivateChannel('conversation.'.$conversationId)`. Payloads carry nothing a
participant may not see; no bodies ever enter logs or the audit log (review-verified).
**Notification** `App\Notifications\NewConversationMessageNotification` (`database` channel, `NotificationService::
  newConversationMessage($message, $recipient)` method, fail-soft like the rest of that service):

- Recipients: if sender is the candidate → every company user with `applicants.manage` for the job's company
  (owner + active members; bounded by team size; one `notifications()->insert`-style loop); if sender is an
  employer-side participant → the application candidate.
- Payload: `event: 'message.received'`, sender display name, job title, `url` → named route `messages.show`
  (relative `/messages/{id}`; NotificationController's existing URL sanitizer only allows same-host/relative URLs,
  consistent). **No message body** (A7). Coalescing per A7 (update existing unread notification for the same
  conversation instead of inserting a duplicate; assert-able in tests).

## 10. Inertia page props, React forms/components, shared types, UI states

**Shared types** `resources/js/types/message.ts` (+ barrel export in `types/index.ts`): `ConversationListItem`
(id, viewer_role `'candidate'|'employer'`, application_status, application_id, job {id,title}, company {id,name,
slug,logo_path}, unread bool, last_message {body, sender_id, created_at, deleted_at} | null, other_party_name,
timestamps), `ConversationMessage` (id, sender_id, sender_name, body (`string | null`), edited_at, deleted_at,
created_at, is_mine), `ConversationPagination` reuse `PaginatedData<ConversationListItem>` (declared in
`types/authorization.ts`) and the existing `Pagination` component's flat shape used across the app.

**Pages**

- `resources/js/pages/messages/index.tsx` — inbox: job title (link to `messages.show`), company name, preview
  (or "No messages yet"), relative timestamp, unread dot + semibold row; recipient name sub-line ("Candidate: …"/
  "Employer: …"); empty state ("No conversations yet — conversations start from an application page"); shared
  `Pagination`; breadcrumb/title via app layout.
- `resources/js/pages/messages/show.tsx` — thread header (job title, company logo+name, link back to the
  application detail page on the viewer's side, link to job public page optional); message list (bubbles by
  sender, name + timestamp per group, day separators minimal — keep simple: per-message meta); own messages show
  an **Edit** (pencil) and **Delete** (trash) action — visible on hover/focus-visible, keyboard operable, each a
  `<button type="button">`; edit swaps the bubble for a pre-filled textarea with Save/Cancel (useForm `put`,
  errors inline); delete opens the shared shadcn `Dialog` for confirmation (body is unrecoverable), then `delete`
  via useForm; tombstones render as an italic "This message was deleted" bubble without actions; edited messages
  show a small "(edited)" suffix (no edit-history UI); "Load earlier messages" button (Inertia `router.get(…?page=n+1)`
  with `preserveScroll: true`); composer card with textarea (`maxLength={2000}`, counter), Enter-to-send
  (Shift+Enter newline), submit disabled while `processing`; 422/error rendering from `useForm`; terminal-state
  banner replacing the composer **and** per-message edit/delete actions when `can_send` false.
  The application show pages hide edit/delete affordances through the same `can_message`/terminal logic.
- Echo bootstrap: `resources/js/lib/echo.ts` — lazily creates one Echo instance **only when**
  `import.meta.env.VITE_REVERB_APP_KEY` is set; otherwise exports `null` and the thread page skips subscription
  (page still fully works; document). Wrapped in try/catch — a Reverb outage never breaks page render. Thread
  page subscribes in `useEffect` (`private-conversation.{id}`), and handles three events by id-dedupe:
  `message.sent` → append new bubble; `message.edited` → patch `body`/`edited_at` (skip re-applying if the
  local message is already tombstoned); `message.deleted` → replace the bubble with the tombstone. Auto-scrolls
  via ref; fires `messages.read` POST when a live message arrives while open (page also marks read server-side on
  render). `useEffect` here is a legitimate external-system subscription. Teardown unsubscribes on unmount.
- `resources/js/lib/navigation.ts` — add `{ title: 'Messages', href: '/messages', icon: MessageSquare, requiredRoles:
  ['job_seeker', 'employer'] }` to `mainNavConfig`.
- `resources/js/pages/{candidate,employer}/applications/show.tsx` — add "Messages" outline button next to existing
  actions when prop `can_message` true (form POST to `messages.open`, `useForm({ }).post(route)`); hidden when
  false; the employer page already has the action area. Both show actions gain `can_message` prop (§4).
- Notification page: verify `resources/js/pages/notifications/index.tsx` renders rows generically; if it
  switches on `event`, add the `message.received` case (name + job title + link). (To be checked at implementation;
  the event value is a new string either way.)
- Unread badge: surface shared prop `messages_unread_count` in the app shell next to the existing notifications
  badge (exact component located at implementation time; pattern already exists for `notifications_unread_count`).

**States**: empty inbox, empty thread, loading/processing (spinner + disabled), send/edit/delete failure (inline
error + toast), edit-in-progress bubble, delete-confirm dialog, tombstone rows, read-only terminal banner,
realtime-disconnected degradation (no indicator needed — next navigation refreshes).

## 11. Test matrix (Pest; target ~35 tests)

Feature (`tests/Feature/Messaging/ConversationTest.php`, `…/MessageTest.php`, `…/MessagesReadTest.php` as fits):

| Area              | Cases                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Open/create       | candidate opens → conversation created & redirect; employer (owner) opens → same conversation; concurrent create race (two requests, unique constraint, exactly one row); **guessed/nonexistent application id → 404**; stranger candidate → 403; unrelated employer (other company) → 403; reviewer member → opens (view) but cannot send; revoked member → 403 on open/show/send; suspended/unapproved employer → 403                                                                                    |
| Send – happy path | candidate → employer persists; employer → candidate persists; body trimmed; message appears in thread payload; `last_message_id`/`last_message_at` updated atomically; sender cursor advanced (sender unread stays 0)                                                                                                                                                                                                                                                                                      |
| Send – validation | empty body, whitespace-only body → 422 `body`; 2001 chars → 422; non-string body → 422; HTML body stored as text and returned escaped (string equality, no rendering assertion needed)                                                                                                                                                                                                                                                                                                                     |
| Send – terminal   | `hired`, `rejected`, `withdrawn` applications → 422 with the read-only message, **no row inserted**, no broadcast, no notification                                                                                                                                                                                                                                                                                                                                                                         |
| Edit              | own-message edit happy path (body replaced, `edited_at` set, returned in thread payload); 422 empty/whitespace-only/2001-char body; other participant → 403; employer sender with revoked membership → 403; terminal application → 422; no notification created; edit after delete (tombstone) → 422                                                                                                                                                                                                       |
| Delete            | own-message delete happy path (soft delete: body stored NULL, `deleted_at` set, row retained); tombstone rendered in thread payload (`body: null`); other participant → 403; revoked member → 403; terminal application → 422; no notification created; inbox preview falls back to previous non-deleted message; badge/cursor unaffected                                                                                                                                                                  |     |
| Read state        | cursor created/updated server-side on `show`; unread flag true for the other party; after visiting thread, unread false; badge aggregate query returns expected count; thread opened twice idempotent                                                                                                                                                                                                                                                                                                      |
| Broadcast         | `Broadcast::fake()` + `Event::fake()`: `MessageSent` dispatched exactly once per send **after commit** (assert via `Event::fake` + transaction structure; with `QUEUE_CONNECTION=sync` the event is processed inline under fake), payload has no body beyond allowed keys, channel name `conversation.{id}`; NOT dispatched on validation failure/terminal block; `MessageEdited`/`MessageDeleted` each dispatched once after commit (delete payload carries no body)                                      |
| Channel auth      | `POST /broadcasting/auth` with `conversation.{id}`: candidate → authorized; owner employer → authorized; reviewer member → authorized (view); unrelated employer → 403; other candidate → 403; channel name with guessed other-conversation id → 403                                                                                                                                                                                                                                                       |
| Notification      | `Notification::fake()`: candidate-sender → sent to each company participant with `applicants.manage` (owner + recruiter, not reviewer); employer-sender → sent to candidate; payload data has `event = message.received`, `url`, `sender_name`, `job_title`, **no `body`**; second message while first unread → still one unread notification (coalesced `data` refreshed); `read_at` set → next send creates a fresh notification; edit and delete actions create **no** notification                     |
| Inbox             | ordering by last activity desc (incl. coalescing of null `last_message_at` via created_at); unread flag per row; employer branch scoped to allowed companies (owner, recruiter, reviewer), wrong-company conversation absent; candidate branch scoped to own applications; pagination (`?page=2`, flat `pagination` prop); selected columns (no eager-load of full message bodies beyond preview); no N+1 assertion via `withCount`-style or query-count guard if repo has one (else assert payload shape) |
| Application pages | `can_message` true for eligible candidate/employer; false on terminal application; POST `messages.open` from application page redirects to `messages.show`; cross-application POST → 403                                                                                                                                                                                                                                                                                                                   |
| Rate limit        | 31st message in a minute → 429                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| Regression        | suite stays green (baseline 720 tests); candidate withdrawal flow unaffected (attempting send after withdraw → 422); Feature 25 notifications page still renders                                                                                                                                                                                                                                                                                                                                           |

No new unit suite required (logic is transactional feature-level); pure predicates reuse `JobApplicationStatus::isTerminal()`.

## 12. Security and performance risks

- **IDOR / cross-tenant access:** every route re-authorizes via `ConversationPolicy`; channel callbacks re-check
  membership/permission on every `/broadcasting/auth` call; HTTP send/edit/delete re-lock and re-check terminal
  status. No client-supplied sender/participant/company ids are trusted (sender = auth user).
- **Edit/delete safety:** mutations are sender-only **and** re-authorized against the live participant predicate
  (A12) — a revoked employer member cannot edit/delete anything, including their own words. Delete destroys the
  body server-side (`NULL`) leaving only a tombstone; the body never travels to other participants via Inertia,
  broadcasts, notifications, or exports. Edit/delete broadcast payloads carry no other participants' data.
- **Authorization drift on revocation:** already-connected WS clients keep receiving until disconnect/reconnect —
  documented residual; HTTP controls (list/show/send) deny immediately. Channel callback evaluates live permission,
  so a revoked member cannot re-authenticate the channel.
- **PII/secrets:** message bodies never appear in notifications, logs, or the audit log. Notification payload is
  name + job title + URL. Logs must record no body (assert in code review; test the notification payload).
- **XSS:** React escapes by default; no HTML injection path; server never echoes body into HTML outside Inertia
  JSON (Inertia JSON-props are safely encoded; `X-Inertia` pages use `json_encode`).
- **Rate limiting / abuse:** `throttle:30,1` send/edit/delete/open, `throttle:60,1` read-marker, body cap 2000.
  No content moderation added here (reports go through Feature 30).
- **Notification storm:** A7 coalescing caps unread message notifications at one per conversation per recipient.
- **DoS via inbox sort:** `COALESCE` ordering loses index use; at expected portfolio scale (thousands of
  conversations per participant) acceptable — **EXPLAIN required**; if slow, switch to two indexed branches or a
  nullable `sort_at` column with composite handling. Recorded as a rollout verification step, not speculative index.
- **Race:** get-or-create unique constraint + insert-catch; cursor upsert idempotent; `lockForUpdate` on the
  application row during send; all multi-write paths in transactions.
- **Queue/Reverb ops:** broadcast events depend on a worker; an enqueue failure throws **after** commit (message
  is safe; request errors visibly — candidate can retry the UI call; acceptable, documented). Reverb down ⇒
  connected clients stop receiving live events but can refresh/retrieve; no data loss.
- **Privacy of history:** messages visible only to participants; application/job closure never deletes them.

## 13. Rollout / compatibility

- **New dependencies (approval at gate):** `composer require laravel/reverb` (self-hosted WS server),
  `npm install laravel-echo pusher-js`. No paid providers; no Pusher account.
- **Scaffolding:** `php artisan install:broadcasting` (publishes `config/broadcasting.php`, creates
  `routes/channels.php`, wires `bootstrap/app.php` `withRouting(..., channels: …)`) or manual equivalent;
  **no generated files may be hand-edited blindly** — review diff before committing.
- **Env:** `.env.example` gains `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=127.0.0.1`,
  `REVERB_PORT=8080`, `REVERB_SCHEME=http`, `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`,
  `VITE_REVERB_SCHEME`; real values only in `.env` (never committed). Local dev requirements section added to
  README/docs: run `php artisan reverb:start` and `php artisan queue:work` (or document `ShouldBroadcastNow`
  alternative A3 if chosen); `composer dev` note updated if applicable.
- **phpunit.xml** stays `BROADCAST_CONNECTION=null`, `QUEUE_CONNECTION=sync` — no change.
- **MySQL verification caveat (same as Feature 34):** the local dev MySQL (`127.0.0.1:3309`) was unreachable at
  the time of writing; migration apply/rollback/re-apply and `EXPLAIN` on MySQL must be run before deployment.
  SQLite in-memory covers CI only.
- **Rollback:** drop the three tables (additive-only changes; no existing tables altered; no backfill). Reverb/Echo
  removal = uninstall deps + delete untracked scaffold; no code paths required at rest.
- **Wayfinder:** run `npm run build` to regenerate route modules after route additions.
- **Interaction with in-flight work:** Feature 34's committed changes and Feature 35 touch disjoint files except
  the two application show pages and `HandleInertiaRequests.php` — coordinate merge order if Feature 34 is not yet
  committed on this branch.

## 14. Verification commands (this repo)

```
php artisan test                          # full suite (baseline 720 passed / 5799 assertions)
php artisan test --filter=Conversation    # open/show/read flows
php artisan test --filter=MessageSent     # broadcast assertions
php artisan test --filter=Message         # send/validation/terminal cases
vendor\bin\pint --test
php vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npm run types:check
npm run check
npm run build                             # regenerates Wayfinder modules
php artisan migrate --database=sqlite --pretend   # DDL review (test suite covers real runs)
# EXPLAIN of the inbox query on a seeded local dataset (implementer provides the exact --pretend/bench snippet)
```

## 15. Implementation sequence

1. **Approval gate** — confirm deps (`laravel/reverb`, `laravel-echo`, `pusher-js`) and A1–A13, especially A1/A11/A12.
2. Backend foundation: `composer require laravel/reverb`; `php artisan install:broadcasting`; review generated
   files; add env keys to `.env`/`.env.example`; `bootstrap/app.php` `channels:` wiring; `npm i laravel-echo
   pusher-js`; `resources/js/lib/echo.ts`.
3. Migrations (3 tables, guarded, idempotent) + models + `ConversationPolicy` + `routes/channels.php` channel.
4. `ConversationService` (open/send/update/delete/markRead) + `MessageSent`, `MessageEdited`, `MessageDeleted`
   events + `NewConversationMessageNotification` + `NotificationService::newConversationMessage`.
5. `StoreConversationMessageRequest` + `UpdateConversationMessageRequest` + controllers
   (`Messages\MessageController` with index/show/store/read/open/update/destroy),
   routes (with throttles), `HandleInertiaRequests` shared `messages_unread_count`, `can_message` props on both
   application show actions.
6. Frontend: types (`message.ts` + barrel), `messages/index.tsx`, `messages/show.tsx`, Echo subscription, nav item,
   "Open conversation" button on both application pages, notifications page event case if applicable.
7. Tests per §11; run §14 commands; fix; review diff.
8. Docs: README/local-dev (Reverb + queue worker), spec §16 implementation notes + deviations; update `docs/specs`
   status → IMPLEMENTED on completion.
9. Deployment checklist: MySQL migration verify + `EXPLAIN` on the dev database (`127.0.0.1:3309`) before release;
   Supervisor/process entries for `reverb:start` and `queue:work`; `.env` `BROADCAST_CONNECTION=reverb`.

## 16. Implementation notes

Implemented, reviewed (independent code-review round, findings fixed — see D10), hardened for scale (D12) and
verified; full suite **768 passed / 768, 6136 assertions** (baseline 720 / 5273); Pint clean; PHPStan 0 errors;
`npm run types:check`, `npm run check` (164 files), `npm run build` all pass (the build also regenerates the
Wayfinder stubs for the new routes).

**Deviations from the spec (D1–D9)**

- **D1.** `ConversationPolicy` (spec name kept) cannot be auto-discovered for two models, so both models are mapped
  explicitly in `AppServiceProvider::boot()` (`ApplicationConversation` → `ConversationPolicy`,
  `ConversationMessage` → `ConversationPolicy`).
- **D2.** Application-keyed authorization lives in the same policy (`viewApplication` / `sendApplication`); the
  `messages.open` endpoint and both application `can_message` props use it. Conversation-keyed `view`/`send` reuse
  the same predicates.
- **D3.** The private channel callback is a class channel, `App\Broadcasting\ConversationChannel::join()`, which
  re-evaluates `ConversationPolicy::view` live. Channel authorization is unit-asserted through `join()` directly
  because phpunit's `BROADCAST_CONNECTION=null` never invokes channel callbacks (`NullBroadcaster::auth()` returns
  immediately, authorizing everyone). The `/broadcasting/auth` wire path (PusherBroadcaster →
  `verifyUserCanAccessChannel` → `AccessDeniedHttpException` → 403) was verified by code inspection, not executed in
  the suite; see D8 for what "works" there means without Reverb running.
- **D4.** A global `Route::bind('message', …)` resolves soft-deleted messages (`ConversationMessage::withTrashed()`)
  so edit-after-delete returns the 422 validation error instead of 404. Scoped to that parameter name only.
- **D5.** `latestMessage` (`ofMany('id','max')`) eager-load selects qualified columns (`conversation_messages.*`);
  an unqualified select produced `ambiguous column name: conversation_id` on SQLite (subquery inner join).
- **D6.** Frontend pages do not import Wayfinder route modules; all URLs are literal strings matching named routes
  (consistent with existing pages, e.g. navigation.ts).
- **D7.** The Vite manifest had to be rebuilt (`npm run build`) before any Inertia feature test rendering the new
  pages could pass — standard for this repo after adding pages.
- **D8.** Real-time delivery itself (Reverb server + queue worker) was not exercised end-to-end — no Reverb server
  is running in this environment. Broadcast assertions use `Event::fake()` (dispatch exactly once, correct channel
  `private-conversation.{id}`, payload keys, delete payload body-free). Manual browser QA of the inbox, thread page,
  edit/delete/tombstones and unread badge has **not** been performed by the agent and must be reported by the user.
- **D9.** The flaky baseline test `test_job_seeker_can_view_application_form` (factory sets `application_deadline`
  to `now`, which `isPast()` treats as past → 302) failed once non-deterministically and passed on re-runs; it is
  pre-existing and unrelated to this feature.
- **D10.** Post-approval review round fixed: W1 ("Load earlier messages" clobbered the visible thread — prop-sync
  effect and the `only: ['messages']` handler now merge by id instead of replacing; auto-scroll only on growing
  max id), W2 (`StoreConversationMessageRequest` did `(string) $this->input('body')` → 500 on array input; now
  `is_string` guard → 422), W3 (added `test_broadcasting_auth_endpoint_enforces_the_channel_callback` — reverb
  driver + `require` of `routes/channels.php` so the callback is registered on the driver serving the request;
  candidate/employer 200, stranger/guest 403, all fully local HMAC), W4 (added tombstone inbox-preview fallback,
  `?page=2` pagination, `messages.read` 204 path, non-string body 422, DB-level unique-application-id assertion),
  W5 (unique-violation detection keys on MySQL driver code 1062 with a 23000+"unique" fallback for SQLite — no
  longer misclassifies FK violations), S1 (dual-role badge double-count fix — unique-id count), S2 (coalescing
  pre-query is fail-soft and uses a `where('data->conversation_id', …)` JSON-path push-down), S3 (employer
  `can_message` now requires `isApprovedEmployer()`), S5 (restored the `@see https://inertiajs.com/shared-data`
  docblock; Messages icon hidden for non-participant roles in both shell headers; inbox preview renders tombstones
  as "This message was deleted" via null body). **D10 deferred/rejected:** S4 (candidate-branch null-company is
  unreachable — `job_postings.company_id` is a non-nullable `restrictOnDelete` FK and phpstan's relation type agrees);
  S2's remaining best-effort race under concurrent coalesced sends (read-then-insert) is accepted and documented.
- **D11.** Spec §10 said send failure shows a toast; implementation renders an inline `InputError` for 422 and a
  flash-toast for success — the 422 path stays inside the form (consistent with repo conventions).
- **D12 (scale round).** After user review of 5M/100M-row readiness: (i) the global
  `messages_unread_count` shared Inertia prop is removed — it was computed on **every** page load with a
  non-indexable COALESCE comparison and full scan of `application_conversations`; unread totals now exist only on
  the inbox page as `unread_total` (two index-driven branches starting from `job_applications`/`job_postings`),
  and both shells render a plain Messages icon without a badge. (ii) Inbox listing is now a UNION of two
  index-driven branches (candidate branch `WHERE a.user_id = ?` via `job_applications_user_id_job_posting_id_unique`;
  employer branch `WHERE j.company_id IN (...)` via the company-id index) instead of the OR that defeated index use,
  ordered by a denormalized indexed `application_conversations.sort_at` (migration
  `2026_11_16_000001_add_conversation_sort_at`, backfilled from `COALESCE(last_message_at, created_at)`); outer
  query uses `OFFSET` pagination with an explicit per-branch `COUNT(*)` for `total`. (iii) Thread history is now
  keyset/cursor pagination (`id DESC`, window of 31 → 30 + `before` cursor) instead of OFFSET — O(page-size) at any
  depth. (iv) Benchmarked on a throwaway `jobapplication_bench` DB (5000 conversations / 300k messages): every
  touched plan is index-driven (`ref`/`eq_ref` on composite or FK-backing indexes, no `ALL` scans), per-user query
  times sub-millisecond — but the union is materialized per page, so at extreme scale a keyset inbox or a
  per-branch top-N merge would be the next step. Bench DB dropped after measuring. **Unverified:** behavior at
  5M+ messages on MySQL (`EXPLAIN` verified only on the small bench set); dev MySQL unreachable during parts of
  this work.

**Results:** new tests
`tests/Feature/Messaging/ConversationTest.php` (23) and `tests/Feature/Messaging/MessageTest.php` (25) — 48 tests,
covering open/create (candidate/owner), cross-tenant denial, reviewer view-only, revocation, terminal read-only,
edit/delete, tombstones, cursor/badge behavior, channel join authorization plus the `/broadcasting/auth` wire path,
broadcast payloads, notification recipients and coalescing, rate limiting, inbox ordering/scoping/pagination,
tombstone preview fallback, shared-prop hygiene (no `messages_unread_count` on non-messaging pages), the 204
read-marker, keyset "Load earlier" window, and the DB unique constraint. Final full suite: **768 passed / 768,
6136 assertions**; Pint clean; PHPStan 0 errors; `npm run types:check`, `npm run check` (164 files), `npm run build`
all pass (build regenerates Wayfinder).

**Verification gap (unchanged from spec §13):** MySQL apply/rollback/re-apply of the migration and `EXPLAIN` on the
dev database (`127.0.0.1:3309`) still cannot be verified here — the dev database was unreachable. Verify before
deployment. Browser QA pending user.
