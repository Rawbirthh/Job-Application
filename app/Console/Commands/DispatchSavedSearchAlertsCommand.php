<?php

namespace App\Console\Commands;

use App\Enums\AlertFrequency;
use App\Models\Job;
use App\Models\SavedSearchAlert;
use App\Models\User;
use App\Services\JobSearchService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchSavedSearchAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saved-searches:dispatch-alerts
                            { frequency : Alert frequency to dispatch (daily|weekly) }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch in-app job alerts for saved searches';

    /**
     * Execute the console command.
     */
    public function handle(JobSearchService $searchService, NotificationService $notificationService): int
    {
        $frequencyValue = trim((string) $this->argument('frequency'));

        $validFrequencies = array_map(
            static fn (AlertFrequency $frequency): string => $frequency->value,
            AlertFrequency::cases()
        );

        if (! in_array($frequencyValue, $validFrequencies, true)) {
            $this->components->error(
                'Invalid frequency ['.$frequencyValue.']. Expected one of: '.implode(', ', $validFrequencies).'.',
            );

            return self::FAILURE;
        }

        $frequency = AlertFrequency::from($frequencyValue);

        $windowStart = now()->subDays(30);

        $usersProcessed = 0;
        $searchesProcessed = 0;
        $notificationsSent = 0;

        User::query()
            ->select(['id'])
            ->whereHas('savedSearches', function (Builder $query) use ($frequency): void {
                $query->where('alerts_enabled', true)
                    ->where('alert_frequency', $frequency->value);
            })
            ->chunkById(250, function (Collection $users) use (
                $frequency,
                $windowStart,
                $searchService,
                $notificationService,
                &$usersProcessed,
                &$searchesProcessed,
                &$notificationsSent,
            ): void {
                foreach ($users as $user) {
                    try {
                        $this->processUser(
                            $user,
                            $frequency,
                            $windowStart,
                            $searchService,
                            $notificationService,
                            $searchesProcessed,
                            $notificationsSent,
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Saved search alert dispatch failed for user', [
                            'user_id' => $user->id,
                            'exception' => $e->getMessage(),
                        ]);

                        continue;
                    }

                    $usersProcessed++;
                }
            });

        $this->components->info(
            "Dispatched [{$frequency->value}] saved search alerts: {$usersProcessed} user(s), ".
            "{$searchesProcessed} search(es), {$notificationsSent} notification(s).",
        );

        return self::SUCCESS;
    }

    private function processUser(
        User $user,
        AlertFrequency $frequency,
        CarbonImmutable $windowStart,
        JobSearchService $searchService,
        NotificationService $notificationService,
        int &$searchesProcessed,
        int &$notificationsSent,
    ): void {
        $searches = $user->savedSearches()
            ->where('alerts_enabled', true)
            ->where('alert_frequency', $frequency->value)
            ->get();

        if ($searches->isEmpty()) {
            return;
        }

        $userSearchIds = $searches->pluck('id');

        // One bounded ledger query: jobs already alerted for ANY of this user's searches
        // inside the ledger recency window.
        $recentlyAlertedJobIds = SavedSearchAlert::query()
            ->whereIn('saved_search_id', $userSearchIds)
            ->where('created_at', '>=', $windowStart)
            ->pluck('job_posting_id')
            ->unique()
            ->values();

        $pickedInRun = collect();
        $runNotified = 0;

        DB::transaction(function () use (
            $searches,
            $recentlyAlertedJobIds,
            $windowStart,
            $searchService,
            $notificationService,
            &$pickedInRun,
            &$runNotified,
            &$searchesProcessed,
        ): void {
            foreach ($searches as $search) {
                $base = $search->last_alerted_at ?? $search->created_at;

                $catchUpStart = $base !== null ? $base->subHour()->max($windowStart) : null;

                $jobsQuery = Job::query()
                    ->select(['id', 'title', 'company_id', 'created_at'])
                    ->with(['company:id,name'])
                    ->published();

                $searchService->visibility($jobsQuery);

                // Never alert older than the 30-day clamp.
                $jobsQuery->where('created_at', '>=', $catchUpStart ?? $windowStart);

                if ($recentlyAlertedJobIds->isNotEmpty() || $pickedInRun->isNotEmpty()) {
                    $jobsQuery->whereNotIn('id', $recentlyAlertedJobIds->merge($pickedInRun)->unique()->values());
                }

                $searchService->apply($jobsQuery, $search->criteria());

                $matches = $jobsQuery->latest()->limit(50)->get();

                if ($matches->isEmpty()) {
                    continue;
                }

                $notificationService->savedSearchMatched(
                    $search,
                    $matches->map(fn (Job $job): array => [
                        'id' => $job->id,
                        'title' => $job->title,
                        'company' => $job->company?->name,
                    ])->all(),
                );

                foreach ($matches as $job) {
                    SavedSearchAlert::firstOrCreate([
                        'saved_search_id' => $search->id,
                        'job_posting_id' => $job->id,
                    ]);
                }

                $pickedInRun = $pickedInRun->merge($matches->modelKeys());

                $search->last_alerted_at = now();
                $search->save();

                $searchesProcessed++;
                $runNotified++;
            }
        });

        $notificationsSent += $runNotified;
    }
}
