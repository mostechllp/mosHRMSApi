<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::first();
        $adminId = $adminUser ? $adminUser->id : 1;

        // Salespersons from screenshot
        $salespersonNames = [
            'Rahul Varma'  => ['first_name' => 'Rahul', 'last_name' => 'Varma', 'email' => 'rahul.varma@mostech.com'],
            'Amina Khan'   => ['first_name' => 'Amina', 'last_name' => 'Khan', 'email' => 'amina.khan@mostech.com'],
            'Karthik Raj'  => ['first_name' => 'Karthik', 'last_name' => 'Raj', 'email' => 'karthik.raj@mostech.com'],
            'Riya Roy'     => ['first_name' => 'Riya', 'last_name' => 'Roy', 'email' => 'riya.roy@mostech.com'],
            'Aarav Mehta'  => ['first_name' => 'Aarav', 'last_name' => 'Mehta', 'email' => 'aarav.mehta@mostech.com'],
        ];

        $employeeMap = [];
        foreach ($salespersonNames as $displayName => $data) {
            $emp = Employee::where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->first();

            if (!$emp) {
                // Find or pick any employee, or create lightweight record if table exists
                $emp = Employee::create([
                    'first_name'    => $data['first_name'],
                    'last_name'     => $data['last_name'],
                    'company_email' => $data['email'],
                    'employee_id'   => 'EMP-' . rand(1000, 9999),
                    'created_by'    => $adminId,
                ]);
            }
            $employeeMap[$displayName] = $emp->id;
        }

        // Customers from screenshot
        $customersData = [
            'ABC Traders' => [
                'company_name'        => 'ABC Traders',
                'customer_type'       => 'Company',
                'contact_person_name' => 'Rajesh Sharma',
                'contact_email'       => 'rajesh@abctraders.com',
                'contact_phone'       => '+91 9876543210',
                'industry'            => 'Retail & E-commerce',
            ],
            'XYZ Pvt Ltd' => [
                'company_name'        => 'XYZ Pvt Ltd',
                'customer_type'       => 'Enterprise',
                'contact_person_name' => 'Vikram Malhotra',
                'contact_email'       => 'vikram@xyzpvtltd.com',
                'contact_phone'       => '+91 9876543211',
                'industry'            => 'Manufacturing',
            ],
            'PQR Solutions' => [
                'company_name'        => 'PQR Solutions',
                'customer_type'       => 'SMB',
                'contact_person_name' => 'Pooja Iyer',
                'contact_email'       => 'pooja@pqrsolutions.com',
                'contact_phone'       => '+91 9876543212',
                'industry'            => 'IT Services & Software',
            ],
            'LMN Group' => [
                'company_name'        => 'LMN Group',
                'customer_type'       => 'Enterprise',
                'contact_person_name' => 'Anil Kapoor',
                'contact_email'       => 'anil@lmngroup.com',
                'contact_phone'       => '+91 9876543213',
                'industry'            => 'Financial Services',
            ],
            'Acme Corp' => [
                'company_name'        => 'Acme Corp',
                'customer_type'       => 'Corporate',
                'contact_person_name' => 'Sunita Rao',
                'contact_email'       => 'sunita@acmecorp.com',
                'contact_phone'       => '+91 9876543214',
                'industry'            => 'Healthcare & Medical',
            ],
            'Globox Ltd' => [
                'company_name'        => 'Globox Ltd',
                'customer_type'       => 'SMB',
                'contact_person_name' => 'Manoj Desai',
                'contact_email'       => 'manoj@globox.com',
                'contact_phone'       => '+91 9876543215',
                'industry'            => 'Logistics & Supply Chain',
            ],
            'Initech Solutions' => [
                'company_name'        => 'Initech Solutions',
                'customer_type'       => 'Enterprise',
                'contact_person_name' => 'Peter Gibbons',
                'contact_email'       => 'peter@initech.com',
                'contact_phone'       => '+91 9876543216',
                'industry'            => 'IT Services & Software',
            ],
        ];

        $customerMap = [];
        foreach ($customersData as $companyName => $custData) {
            $customer = Customer::firstOrCreate(
                ['company_name' => $companyName],
                array_merge($custData, ['created_by' => $adminId])
            );
            $customerMap[$companyName] = $customer->id;
        }

        // The 7 opportunities from Screenshot 3
        $opportunities = [
            [
                'opportunity_id'        => 'OPP-0001',
                'name'                  => 'ABC Traders HRMS Rollout',
                'customer_company'      => 'ABC Traders',
                'product'               => 'HRMS',
                'products'              => ['HRMS'],
                'estimated_value'       => 200000.00,
                'currency'              => 'INR',
                'stage'                 => 'New',
                'probability'           => 20.00,
                'expected_closing_date' => '2026-10-15',
                'assigned_salesperson'  => 'Rahul Varma',
                'priority'              => 'High',
                'lead_source'           => 'Website',
                'competitor'            => 'Keka, Darwinbox',
                'decision_maker'        => 'CTO, HR Head',
                'customer_requirements' => 'End to end HRMS with attendance and leave integration.',
                'next_action'           => 'Initial product discovery call on Thursday',
            ],
            [
                'opportunity_id'        => 'OPP-0002',
                'name'                  => 'XYZ Pvt Ltd ERP Migration',
                'customer_company'      => 'XYZ Pvt Ltd',
                'product'               => 'ERP',
                'products'              => ['ERP'],
                'estimated_value'       => 500000.00,
                'currency'              => 'INR',
                'stage'                 => 'Qualified',
                'probability'           => 40.00,
                'expected_closing_date' => '2026-11-20',
                'assigned_salesperson'  => 'Amina Khan',
                'priority'              => 'High',
                'lead_source'           => 'Referral',
                'competitor'            => 'SAP, Oracle NetSuite',
                'decision_maker'        => 'COO, VP of Engineering',
                'customer_requirements' => 'Migration from legacy on-premise ERP to cloud HRMS/ERP suite.',
                'next_action'           => 'Architecture review meeting scheduled',
            ],
            [
                'opportunity_id'        => 'OPP-0003',
                'name'                  => 'PQR Solutions CRM Setup',
                'customer_company'      => 'PQR Solutions',
                'product'               => 'CRM',
                'products'              => ['CRM'],
                'estimated_value'       => 150000.00,
                'currency'              => 'INR',
                'stage'                 => 'Proposal Sent',
                'probability'           => 60.00,
                'expected_closing_date' => '2026-10-05',
                'assigned_salesperson'  => 'Karthik Raj',
                'priority'              => 'Medium',
                'lead_source'           => 'Cold Call',
                'competitor'            => 'HubSpot, Zoho',
                'decision_maker'        => 'Head of Sales',
                'customer_requirements' => 'Custom sales pipeline with quotation generation module.',
                'next_action'           => 'Follow up on proposal feedback by Friday',
            ],
            [
                'opportunity_id'        => 'OPP-0004',
                'name'                  => 'LMN Group HRMS Upgrade',
                'customer_company'      => 'LMN Group',
                'product'               => 'HRMS',
                'products'              => ['HRMS'],
                'estimated_value'       => 300000.00,
                'currency'              => 'INR',
                'stage'                 => 'Negotiation',
                'probability'           => 70.00,
                'expected_closing_date' => '2026-09-28',
                'assigned_salesperson'  => 'Riya Roy',
                'priority'              => 'High',
                'lead_source'           => 'LinkedIn',
                'competitor'            => 'Workday',
                'decision_maker'        => 'CHRO, CFO',
                'customer_requirements' => 'Upgrade from basic HR to enterprise payroll & compliance package.',
                'next_action'           => 'Commercial terms discussion with finance team',
            ],
            [
                'opportunity_id'        => 'OPP-0005',
                'name'                  => 'Acme Corp Payroll Renewal',
                'customer_company'      => 'Acme Corp',
                'product'               => 'Payroll',
                'products'              => ['Payroll'],
                'estimated_value'       => 120000.00,
                'currency'              => 'INR',
                'stage'                 => 'Won',
                'probability'           => 100.00,
                'expected_closing_date' => '2026-09-15',
                'assigned_salesperson'  => 'Riya Roy',
                'priority'              => 'Medium',
                'lead_source'           => 'Existing Customer',
                'competitor'            => 'None',
                'decision_maker'        => 'Payroll Manager',
                'customer_requirements' => 'Annual payroll subscription renewal for 250 employees.',
                'next_action'           => 'Renewal contract signed and active',
            ],
            [
                'opportunity_id'        => 'OPP-0006',
                'name'                  => 'Globox Attendance System',
                'customer_company'      => 'Globox Ltd',
                'product'               => 'Attendance System',
                'products'              => ['Attendance System'],
                'estimated_value'       => 80000.00,
                'currency'              => 'INR',
                'stage'                 => 'Lost',
                'probability'           => 0.00,
                'expected_closing_date' => '2026-09-05',
                'assigned_salesperson'  => 'Aarav Mehta',
                'priority'              => 'Low',
                'lead_source'           => 'Campaign',
                'competitor'            => 'Biometric hardware vendor local',
                'decision_maker'        => 'Admin Manager',
                'customer_requirements' => 'Facial recognition attendance system.',
                'next_action'           => 'Closed lost due to budget constraints',
            ],
            [
                'opportunity_id'        => 'OPP-0007',
                'name'                  => 'Initech Custom Development',
                'customer_company'      => 'Initech Solutions',
                'product'               => 'Custom Development',
                'products'              => ['Custom Development'],
                'estimated_value'       => 800000.00,
                'currency'              => 'INR',
                'stage'                 => 'Qualified',
                'probability'           => 35.00,
                'expected_closing_date' => '2026-12-10',
                'assigned_salesperson'  => 'Karthik Raj',
                'priority'              => 'High',
                'lead_source'           => 'Event',
                'competitor'            => 'Custom Agency',
                'decision_maker'        => 'VP Technology',
                'customer_requirements' => 'Custom API integrations with SAP and biometric devices.',
                'next_action'           => 'Scope of work document drafting',
            ],
        ];

        foreach ($opportunities as $item) {
            $customerName    = $item['customer_company'];
            $salespersonName = $item['assigned_salesperson'];

            Opportunity::updateOrCreate(
                ['opportunity_id' => $item['opportunity_id']],
                [
                    'name'                    => $item['name'],
                    'customer_id'             => $customerMap[$customerName] ?? 1,
                    'contact_person'          => $customersData[$customerName]['contact_person_name'] ?? 'Contact Person',
                    'product'                 => $item['product'],
                    'products'                => $item['products'],
                    'estimated_value'         => $item['estimated_value'],
                    'currency'                => $item['currency'],
                    'stage'                   => $item['stage'],
                    'probability'             => $item['probability'],
                    'expected_closing_date'   => $item['expected_closing_date'],
                    'assigned_salesperson_id' => $employeeMap[$salespersonName] ?? 1,
                    'sales_team'              => 'Direct Sales',
                    'priority'                => $item['priority'],
                    'lead_source'             => $item['lead_source'],
                    'competitor'              => $item['competitor'],
                    'decision_maker'          => $item['decision_maker'],
                    'customer_requirements'   => $item['customer_requirements'],
                    'next_action'             => $item['next_action'],
                    'notes'                   => 'Created from opportunity management setup.',
                    'created_by'              => $adminId,
                ]
            );
        }
    }
}
