<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmPermission extends Model
{
    /**
     * All recognised CRM permission keys, matching the UI rows.
     */
    public const PERMISSION_KEYS = [
        'view_all_customers',
        'create_leads',
        'assign_leads',
        'delete_customer_records',
        'edit_quotations',
        'view_sales_reports',
        'change_opportunity_stages',
    ];

    /**
     * Default permissions per role slug.
     *   role_id => [permission_key => is_allowed]
     *
     * Role IDs:
     *   1 = Super Admin  (all true – cannot be restricted)
     *   2 = Admin
     *   3 = Sales Manager
     *   4 = Salesperson
     *   5 = Support
     */
    public const DEFAULTS = [
        1 => [ // Super Admin – always full access
            'view_all_customers'        => true,
            'create_leads'              => true,
            'assign_leads'              => true,
            'delete_customer_records'   => true,
            'edit_quotations'           => true,
            'view_sales_reports'        => true,
            'change_opportunity_stages' => true,
        ],
        2 => [ // Admin
            'view_all_customers'        => true,
            'create_leads'              => true,
            'assign_leads'              => true,
            'delete_customer_records'   => true,
            'edit_quotations'           => true,
            'view_sales_reports'        => true,
            'change_opportunity_stages' => true,
        ],
        3 => [ // Sales Manager
            'view_all_customers'        => true,
            'create_leads'              => true,
            'assign_leads'              => true,
            'delete_customer_records'   => false,
            'edit_quotations'           => true,
            'view_sales_reports'        => true,
            'change_opportunity_stages' => true,
        ],
        4 => [ // Salesperson
            'view_all_customers'        => false,
            'create_leads'              => true,
            'assign_leads'              => false,
            'delete_customer_records'   => false,
            'edit_quotations'           => false,
            'view_sales_reports'        => true,
            'change_opportunity_stages' => true,
        ],
        5 => [ // Support
            'view_all_customers'        => false,
            'create_leads'              => false,
            'assign_leads'              => false,
            'delete_customer_records'   => false,
            'edit_quotations'           => false,
            'view_sales_reports'        => false,
            'change_opportunity_stages' => false,
        ],
    ];

    protected $fillable = [
        'role_id',
        'permission_key',
        'is_allowed',
    ];

    protected $casts = [
        'is_allowed' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
