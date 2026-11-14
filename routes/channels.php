<?php

use App\Broadcasting\ConversationChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The only private channel of the application. Whoever asks for a
| subscription must pass exactly the same policy used by the HTTP
| endpoints — possession of a channel name is never authorization.
| Membership/permission is re-read on every subscription request, so a
| revoked company member cannot re-subscribe.
|
*/
Broadcast::channel('conversation.{conversationId}', ConversationChannel::class);
