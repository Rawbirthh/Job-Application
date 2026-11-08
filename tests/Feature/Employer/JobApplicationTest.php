<?php

namespace Tests\Feature\Employer;

use App\Models\CandidateProfile;
use App\Models\CandidateResume;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobApplicationAnswer;
use App\Models\JobScreeningQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
        Storage::fake('local');
    }

    public function test_guests_cannot_access_employer_application_routes(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();

        $this->get(route('employer.jobs.applicants', $job))->assertRedirect(route('login'));
        $this->get(route('employer.applications.show', $application))->assertRedirect(route('login'));
        $this->get(route('employer.applications.resume.download', $application))->assertRedirect(route('login'));
    }

    public function test_unapproved_employers_cannot_access_employer_application_routes(): void
    {
        $employer = User::factory()->employer()->create(); // unapproved
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();

        $this->actingAs($employer)
            ->get(route('employer.jobs.applicants', $job))
            ->assertForbidden();

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertForbidden();
    }

    public function test_employer_can_view_applicants_for_their_job(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        CandidateProfile::factory()->for($jobSeeker, 'user')->create(['headline' => 'Senior Developer']);
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($jobSeeker, 'user')
            ->for($resume, 'candidateResume')
            ->create(['status' => 'submitted']);

        $this->actingAs($employer)
            ->get(route('employer.jobs.applicants', $job))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/jobs/applicants/index')
                ->has('job')
                ->has('applications.data', 1)
                ->where('applications.data.0.candidate.name', $jobSeeker->name)
                ->where('applications.data.0.candidate.headline', 'Senior Developer')
                ->where('applications.data.0.status', 'submitted')
            );
    }

    public function test_employer_cannot_view_applicants_for_another_companys_job(): void
    {
        $employer1 = User::factory()->approvedEmployer()->create();
        $company1 = Company::factory()->for($employer1, 'owner')->create();
        $job1 = Job::factory()->for($company1)->create();

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();

        $this->actingAs($employer2)
            ->get(route('employer.jobs.applicants', $job1))
            ->assertForbidden();
    }

    public function test_employer_can_view_application_detail(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        CandidateProfile::factory()->for($jobSeeker, 'user')->create([
            'headline' => 'Backend Engineer',
            'summary' => 'Experienced Laravel developer.',
            'skills' => ['PHP', 'Laravel', 'MySQL'],
        ]);
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create([
            'original_filename' => 'resume.pdf',
            'size' => 12345,
            'mime_type' => 'application/pdf',
        ]);

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($jobSeeker, 'user')
            ->for($resume, 'candidateResume')
            ->create([
                'status' => 'reviewing',
                'cover_letter' => 'I am very excited about this role.',
            ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/applications/show')
                ->where('application.id', $application->id)
                ->where('application.status', 'reviewing')
                ->where('application.cover_letter', 'I am very excited about this role.')
                ->where('candidate.name', $jobSeeker->name)
                ->where('candidate.headline', 'Backend Engineer')
                ->where('candidate.summary', 'Experienced Laravel developer.')
                ->where('candidate.skills', ['PHP', 'Laravel', 'MySQL'])
                ->where('application.resume.original_filename', 'resume.pdf')
            );
    }

    public function test_employer_cannot_view_application_detail_for_another_companys_job(): void
    {
        $employer1 = User::factory()->approvedEmployer()->create();
        $company1 = Company::factory()->for($employer1, 'owner')->create();
        $job1 = Job::factory()->for($company1)->create();
        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job1, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();

        $this->actingAs($employer2)
            ->get(route('employer.applications.show', $application))
            ->assertForbidden();
    }

    public function test_employer_can_download_applicant_resume(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();

        $filePath = 'resumes/test-resume.pdf';
        Storage::disk('local')->put($filePath, 'fake-pdf-content');

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create([
            'path' => $filePath,
            'original_filename' => 'candidate-cv.pdf',
        ]);

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($jobSeeker, 'user')
            ->for($resume, 'candidateResume')
            ->create();

        $this->actingAs($employer)
            ->get(route('employer.applications.resume.download', $application))
            ->assertOk()
            ->assertDownload('candidate-cv.pdf');
    }

    public function test_unrelated_employer_cannot_download_applicant_resume(): void
    {
        $employer1 = User::factory()->approvedEmployer()->create();
        $company1 = Company::factory()->for($employer1, 'owner')->create();
        $job1 = Job::factory()->for($company1)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        $filePath = 'resumes/test-resume.pdf';
        Storage::disk('local')->put($filePath, 'fake-pdf-content');

        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create([
            'path' => $filePath,
        ]);

        $application = JobApplication::factory()
            ->for($job1, 'jobPosting')
            ->for($jobSeeker, 'user')
            ->for($resume, 'candidateResume')
            ->create();

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();

        $this->actingAs($employer2)
            ->get(route('employer.applications.resume.download', $application))
            ->assertForbidden();
    }

    public function test_employer_sees_screening_answers_on_application_detail(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();

        $application = JobApplication::factory()
            ->for($job, 'jobPosting')
            ->for($jobSeeker, 'user')
            ->for($resume, 'candidateResume')
            ->create();

        $question = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Question label',
            'options' => null,
            'is_required' => true,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        JobApplicationAnswer::create([
            'job_application_id' => $application->id,
            'job_screening_question_id' => $question->id,
            'answer_text' => 'Employer visible answer',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('screening_answers', 1)
                ->where('screening_answers.0.question_label', 'Question label')
                ->where('screening_answers.0.answer_text', 'Employer visible answer')
            );
    }

    public function test_cross_company_employer_cannot_view_application_detail_with_answers(): void
    {
        $employer1 = User::factory()->approvedEmployer()->create();
        $company1 = Company::factory()->for($employer1, 'owner')->create();
        $job1 = Job::factory()->for($company1)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job1, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();

        $employer2 = User::factory()->approvedEmployer()->create();
        Company::factory()->for($employer2, 'owner')->create();

        $this->actingAs($employer2)
            ->get(route('employer.applications.show', $application))
            ->assertForbidden();
    }

    public function test_deactivated_job_question_still_readable_on_prior_application(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->for($employer, 'owner')->create();
        $job = Job::factory()->for($company)->create();

        $jobSeeker = User::factory()->jobSeeker()->create();
        $resume = CandidateResume::factory()->for($jobSeeker, 'user')->create();
        $application = JobApplication::factory()->for($job, 'jobPosting')->for($jobSeeker, 'user')->for($resume, 'candidateResume')->create();

        $question = JobScreeningQuestion::create([
            'job_posting_id' => $job->id,
            'type' => 'short_text',
            'label' => 'Deactivated question',
            'options' => null,
            'is_required' => true,
            'sort_order' => 0,
            'is_active' => false,
        ]);
        JobApplicationAnswer::create([
            'job_application_id' => $application->id,
            'job_screening_question_id' => $question->id,
            'answer_text' => 'Prior answer',
        ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('screening_answers', 1)
                ->where('screening_answers.0.question_label', 'Deactivated question')
                ->where('screening_answers.0.answer_text', 'Prior answer')
            );
    }
}
