<?php

namespace App\Http\Requests\Messaging;

class UpdateConversationMessageRequest extends StoreConversationMessageRequest
{
    // Identical body rules as the store request; authorization is enforced
    // by the controller via ConversationPolicy::edit.
}
