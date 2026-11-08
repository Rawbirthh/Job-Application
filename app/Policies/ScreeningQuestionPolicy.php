<?php

namespace App\Policies;

use App\Models\ScreeningQuestion;
use App\Models\User;

class ScreeningQuestionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function create(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function update(User $actor, ScreeningQuestion $question): bool
    {
        return $actor->isApprovedEmployer() && $question->owner_user_id === $actor->id;
    }

    public function delete(User $actor, ScreeningQuestion $question): bool
    {
        return $actor->isApprovedEmployer() && $question->owner_user_id === $actor->id;
    }
}
