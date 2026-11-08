<?php

namespace App\Policies;

use App\Models\ScreeningQuestionTemplate;
use App\Models\User;

class ScreeningQuestionTemplatePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function create(User $actor): bool
    {
        return $actor->isApprovedEmployer();
    }

    public function view(User $actor, ScreeningQuestionTemplate $template): bool
    {
        return $actor->isApprovedEmployer() && $template->owner_user_id === $actor->id;
    }

    public function update(User $actor, ScreeningQuestionTemplate $template): bool
    {
        return $actor->isApprovedEmployer() && $template->owner_user_id === $actor->id;
    }

    public function delete(User $actor, ScreeningQuestionTemplate $template): bool
    {
        return $actor->isApprovedEmployer() && $template->owner_user_id === $actor->id;
    }
}
