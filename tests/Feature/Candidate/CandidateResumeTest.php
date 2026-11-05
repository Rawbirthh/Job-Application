<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateResume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class CandidateResumeTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
        Storage::fake('local');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('candidate.resumes.index'))->assertRedirect(route('login'));
        $this->post(route('candidate.resumes.upload'))->assertRedirect(route('login'));
    }

    public function test_platform_admin_without_job_seeker_gets_403(): void
    {
        $user = User::factory()->platformAdmin()->create();

        $this->actingAs($user)->get(route('candidate.resumes.index'))->assertForbidden();
    }

    public function test_job_seeker_can_view_resume_index_empty(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('candidate.resumes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/resumes/index')
                ->where('resume', null)
            );
    }

    public function test_job_seeker_can_upload_resume(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('candidate.resumes.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('candidate.resumes.index'));

        $this->assertDatabaseHas('candidate_resumes', [
            'user_id' => $user->id,
            'original_filename' => 'resume.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $resume = CandidateResume::query()->where('user_id', $user->id)->firstOrFail();
        Storage::disk('local')->assertExists($resume->path);
    }

    public function test_non_pdf_file_is_rejected(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file = UploadedFile::fake()->create('resume.txt', 100, 'text/plain');

        $response = $this->actingAs($user)->post(route('candidate.resumes.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('candidate_resumes', 0);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file = UploadedFile::fake()->create('resume.pdf', 6000, 'application/pdf'); // 6MB

        $response = $this->actingAs($user)->post(route('candidate.resumes.upload'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('candidate_resumes', 0);
    }

    public function test_job_seeker_can_replace_resume(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file1 = UploadedFile::fake()->create('first.pdf', 100, 'application/pdf');
        $this->actingAs($user)->post(route('candidate.resumes.upload'), ['file' => $file1]);

        $resume1 = CandidateResume::query()->where('user_id', $user->id)->firstOrFail();
        $path1 = $resume1->path;

        $file2 = UploadedFile::fake()->create('second.pdf', 150, 'application/pdf');
        $this->actingAs($user)->post(route('candidate.resumes.upload'), ['file' => $file2]);

        $this->assertDatabaseCount('candidate_resumes', 1);
        $resume2 = CandidateResume::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('second.pdf', $resume2->original_filename);
        $this->assertNotSame($path1, $resume2->path);
        Storage::disk('local')->assertExists($resume2->path);
    }

    public function test_job_seeker_can_delete_resume(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
        $this->actingAs($user)->post(route('candidate.resumes.upload'), ['file' => $file]);

        $resume = CandidateResume::query()->where('user_id', $user->id)->firstOrFail();

        $response = $this->actingAs($user)->delete(route('candidate.resumes.delete', $resume));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('candidate.resumes.index'));

        $this->assertDatabaseMissing('candidate_resumes', ['id' => $resume->id]);
        Storage::disk('local')->assertMissing($resume->path);
    }

    public function test_job_seeker_can_download_resume(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $file = UploadedFile::fake()->create('my_resume.pdf', 100, 'application/pdf');
        $this->actingAs($user)->post(route('candidate.resumes.upload'), ['file' => $file]);

        $resume = CandidateResume::query()->where('user_id', $user->id)->firstOrFail();

        $response = $this->actingAs($user)->get(route('candidate.resume.download', $resume));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_cannot_access_other_users_resume(): void
    {
        $owner = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $file = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
        $this->actingAs($owner)->post(route('candidate.resumes.upload'), ['file' => $file]);

        $resume = CandidateResume::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)->get(route('candidate.resume.download', $resume))
            ->assertForbidden();

        $this->actingAs($other)->delete(route('candidate.resumes.delete', $resume))
            ->assertForbidden();
    }
}
