<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Recommendation\JobRecommendationService;
use App\Services\Recommendation\RecommendationItem;
use App\Services\Recommendation\RecommendationReason;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly JobRecommendationService $recommendationService,
    ) {}

    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        $result = $this->recommendationService->recommend($user);

        $paginator = $result['paginator'];
        $paginator->through(fn (RecommendationItem $item): array => $this->itemPayload($item));

        return Inertia::render('candidate/recommendations', [
            'recommendations' => $paginator,
            'mode' => $result['mode'],
            'profileSummary' => $result['profileSummary'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(RecommendationItem $item): array
    {
        $job = $item->job;

        return [
            'id' => (int) $job->id,
            'title' => $job->title,
            'location' => $job->location,
            'salary_min' => $job->salary_min !== null ? (float) $job->salary_min : null,
            'salary_max' => $job->salary_max !== null ? (float) $job->salary_max : null,
            'salary_currency' => $job->salary_currency,
            'salary_negotiable' => (bool) $job->salary_negotiable,
            'application_deadline' => $job->application_deadline?->toIso8601String(),
            'published_at' => $job->created_at?->toIso8601String(),
            'company' => $job->company ? [
                'id' => (int) $job->company->id,
                'name' => $job->company->name,
                'slug' => $job->company->slug,
                'logo_url' => $job->company->logo_url,
            ] : null,
            'category' => $job->category ? [
                'id' => (int) $job->category->id,
                'name' => $job->category->name,
            ] : null,
            'employment_type' => $job->employmentType ? [
                'id' => (int) $job->employmentType->id,
                'name' => $job->employmentType->name,
            ] : null,
            'workplace_arrangement' => $job->workplaceArrangement ? [
                'id' => (int) $job->workplaceArrangement->id,
                'name' => $job->workplaceArrangement->name,
            ] : null,
            'experience_level' => $job->experienceLevel ? [
                'id' => (int) $job->experienceLevel->id,
                'name' => $job->experienceLevel->name,
            ] : null,
            'match_score' => $item->score,
            'reasons' => array_map(fn (RecommendationReason $reason): array => [
                'code' => $reason->code,
                'label' => $reason->label,
                'detail' => $reason->detail,
                'values' => $reason->values,
            ], $item->reasons),
            'has_applied' => $item->hasApplied,
            'is_saved' => $item->isSaved,
        ];
    }
}
