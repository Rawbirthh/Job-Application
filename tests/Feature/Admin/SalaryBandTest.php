<?php

namespace Tests\Feature\Admin;

use App\Models\PayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class SalaryBandTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_can_create_a_salary_band(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $payPeriod = PayPeriod::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.salary-bands.store'), [
                'pay_period_id' => $payPeriod->id,
                'label' => 'Senior Level',
                'currency' => 'USD',
                'lower_bound' => 0,
                'upper_bound' => 50000,
                'is_active' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.salary-bands.index'));

        $this->assertDatabaseHas('salary_bands', [
            'label' => 'Senior Level',
            'currency' => 'USD',
            'pay_period_id' => $payPeriod->id,
            'lower_bound' => 0,
            'upper_bound' => 50000,
            'is_active' => true,
        ]);
    }
}
