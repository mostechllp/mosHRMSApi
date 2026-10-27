<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employee $employee;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create();

        // Create employee
        $this->employee = Employee::create([
            'user_id'       => $this->user->id,
            'first_name'    => 'Rahul',
            'last_name'     => 'Varma',
            'employee_id'   => 'EMP-1001',
            'company_email' => 'rahul.varma@mostech.com',
            'created_by'    => $this->user->id,
        ]);

        // Create customer
        $this->customer = Customer::create([
            'company_name'        => 'ABC Traders',
            'customer_type'       => 'Company',
            'contact_person_name' => 'Rajesh Sharma',
            'contact_email'       => 'rajesh@abctraders.com',
            'contact_phone'       => '+91 9876543210',
            'created_by'          => $this->user->id,
        ]);
    }

    public function test_can_fetch_opportunity_form_options(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/crm/opportunities/form-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'next_opportunity_id',
                    'customers',
                    'salespersons',
                    'sales_teams',
                    'lead_sources',
                    'products',
                    'stages',
                    'priorities',
                    'currencies',
                ],
            ]);
    }

    public function test_can_create_opportunity(): void
    {
        $payload = [
            'name'                    => 'ABC Traders HRMS Rollout',
            'customer_id'             => $this->customer->id,
            'contact_person'          => 'Rajesh Sharma',
            'lead_source'             => 'Website',
            'products'                => ['HRMS'],
            'product'                 => 'HRMS',
            'description'             => 'Complete HRMS implementation',
            'estimated_value'         => 200000.00,
            'currency'                => 'INR',
            'stage'                   => 'New',
            'probability'             => 20,
            'expected_closing_date'   => '2026-10-15',
            'assigned_salesperson_id' => $this->employee->id,
            'sales_team'              => 'Direct Sales',
            'priority'                => 'High',
            'competitor'              => 'Oracle, SAP',
            'decision_maker'          => 'CTO, CFO',
            'customer_requirements'   => 'Need biometric integration and attendance tracking',
            'next_action'             => 'Send proposal by Friday',
            'notes'                   => 'Key prospect for Q4',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/admin/crm/opportunities', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.opportunity.name', 'ABC Traders HRMS Rollout')
            ->assertJsonPath('data.opportunity.stage', 'New');

        $this->assertDatabaseHas('opportunities', [
            'name'        => 'ABC Traders HRMS Rollout',
            'customer_id' => $this->customer->id,
            'stage'       => 'New',
        ]);
    }

    public function test_can_fetch_opportunity_stats(): void
    {
        Opportunity::create([
            'name'                    => 'ABC Traders HRMS Rollout',
            'customer_id'             => $this->customer->id,
            'estimated_value'         => 200000,
            'currency'                => 'INR',
            'stage'                   => 'New',
            'probability'             => 20,
            'assigned_salesperson_id' => $this->employee->id,
            'priority'                => 'High',
            'created_by'              => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/crm/opportunities/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total',
                    'open',
                    'pipeline_value',
                    'formatted_pipeline_value',
                    'won',
                    'lost',
                    'closing_this_month',
                ],
            ]);
    }

    public function test_can_fetch_opportunity_pipeline(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/admin/crm/opportunities/pipeline');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_can_update_opportunity_stage(): void
    {
        $opportunity = Opportunity::create([
            'name'                    => 'Test Opportunity',
            'customer_id'             => $this->customer->id,
            'estimated_value'         => 150000,
            'currency'                => 'INR',
            'stage'                   => 'New',
            'probability'             => 20,
            'assigned_salesperson_id' => $this->employee->id,
            'created_by'              => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/admin/crm/opportunities/{$opportunity->id}/stage", [
                'stage'       => 'Qualified',
                'probability' => 40,
                'notes'       => 'Customer met the qualification criteria',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stage', 'Qualified')
            ->assertJsonPath('data.probability', '40.00');

        $this->assertEquals('Qualified', $opportunity->fresh()->stage);
    }
}
