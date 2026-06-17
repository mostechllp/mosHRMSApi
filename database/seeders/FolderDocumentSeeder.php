<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FolderDocumentSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('folders')->insert([
            ['id' => 1, 'name' => 'HR Policies & Procedures', 'created_by' => 1, 'deleted_by' => null, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28', 'deleted_at' => null],
            ['id' => 2, 'name' => 'Supplier Agreements',      'created_by' => 1, 'deleted_by' => null, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28', 'deleted_at' => null],
            ['id' => 3, 'name' => 'Employee Contracts',       'created_by' => 1, 'deleted_by' => null, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28', 'deleted_at' => null],
            ['id' => 4, 'name' => 'Clinical Protocols',       'created_by' => 1, 'deleted_by' => null, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28', 'deleted_at' => null],
            ['id' => 5, 'name' => 'Accreditation & Licenses', 'created_by' => 1, 'deleted_by' => null, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28', 'deleted_at' => null],
        ]);

        DB::table('documents')->insert([
            [
                'id'          => 1,
                'name'        => 'Staff Handbook 2026',
                'type'        => 'hr',
                'description' => 'Official staff policies, code of conduct and HR procedures',
                'file_path'   => 'documents/staff_handbook_2026.pdf',
                'party_id'    => null,
                'folder_id'   => 1,
                'share_with'  => null,
                'expiry_date' => '2026-12-31',
                'created_by'  => 1,
                'deleted_by'  => null,
                'created_at'  => '2026-06-03 13:00:28',
                'updated_at'  => '2026-06-03 13:00:28',
                'deleted_at'  => null,
            ],
            [
                'id'          => 2,
                'name'        => 'Gulf Medical Supplies — Annual Contract 2026',
                'type'        => 'agreements',
                'description' => 'Annual supply agreement for surgical consumables and PPE',
                'file_path'   => 'documents/gulfmedical_contract_2026.pdf',
                'party_id'    => 1,
                'folder_id'   => 2,
                'share_with'  => '[1, 2]',
                'expiry_date' => '2026-12-31',
                'created_by'  => 1,
                'deleted_by'  => null,
                'created_at'  => '2026-06-03 13:00:28',
                'updated_at'  => '2026-06-03 13:00:28',
                'deleted_at'  => null,
            ],
            [
                'id'          => 3,
                'name'        => 'DHA Healthcare Facility License',
                'type'        => 'organization',
                'description' => 'Dubai Health Authority facility operating license',
                'file_path'   => 'documents/dha_license_2026.pdf',
                'party_id'    => 3,
                'folder_id'   => 5,
                'share_with'  => null,
                'expiry_date' => '2027-03-31',
                'created_by'  => 1,
                'deleted_by'  => null,
                'created_at'  => '2026-06-03 13:00:28',
                'updated_at'  => '2026-06-03 13:00:28',
                'deleted_at'  => null,
            ],
            [
                'id'          => 4,
                'name'        => 'Infection Control Protocol v3.2',
                'type'        => 'hr',
                'description' => 'Standard operating procedure for infection prevention and control',
                'file_path'   => 'documents/infection_control_protocol_v3.2.pdf',
                'party_id'    => null,
                'folder_id'   => 4,
                'share_with'  => null,
                'expiry_date' => null,
                'created_by'  => 1,
                'deleted_by'  => null,
                'created_at'  => '2026-06-03 13:00:28',
                'updated_at'  => '2026-06-03 13:00:28',
                'deleted_at'  => null,
            ],
        ]);
    }
}
