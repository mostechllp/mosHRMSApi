<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ProposalExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreProposalRequest;
use App\Http\Requests\CRM\UpdateProposalRequest;
use App\Models\Company;
use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProposalApiController extends ApiController
{
    /**
     * Display a listing of proposals with search, filters, and pagination.
     * GET /api/admin/crm/proposals
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) {
            return $this->success($query->get(), 'Proposals retrieved successfully');
        }

        return $this->success($query->paginate($perPage), 'Proposals retrieved successfully');
    }

    /**
     * Store a newly created proposal.
     * POST /api/admin/crm/proposals
     */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $proposal = Proposal::create($validated);
            DB::commit();

            return $this->success(
                $proposal->load('customerRelation'),
                'Proposal created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create proposal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified proposal (Edit/View details).
     * GET /api/admin/crm/proposals/{proposal}
     */
    public function show(Proposal $proposal): JsonResponse
    {
        $proposal->load(['customerRelation', 'creator:id,username,email', 'updater:id,username,email']);

        return $this->success($proposal, 'Proposal details retrieved successfully');
    }

    /**
     * Update the specified proposal in storage.
     * PUT/PATCH /api/admin/crm/proposals/{proposal}
     */
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        DB::beginTransaction();
        try {
            $proposal->update($validated);
            DB::commit();

            return $this->success(
                $proposal->fresh(['customerRelation', 'updater:id,username,email']),
                'Proposal updated successfully'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update proposal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified proposal from storage.
     * DELETE /api/admin/crm/proposals/{proposal}
     */
    public function destroy(Proposal $proposal): JsonResponse
    {
        try {
            $proposal->update(['deleted_by' => Auth::id()]);
            $proposal->delete();

            return $this->success(null, 'Proposal deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete proposal: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quick update status endpoint.
     * POST /api/admin/crm/proposals/{proposal}/update-status
     */
    public function updateStatus(Request $request, Proposal $proposal): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(Proposal::STATUSES)],
        ]);

        $proposal->update([
            'status'     => $request->input('status'),
            'updated_by' => Auth::id(),
        ]);

        return $this->success($proposal, 'Proposal status updated successfully');
    }

    /**
     * Get form options and next recommended S.No.
     * GET /api/admin/crm/proposals/form-options
     */
    public function formOptions(): JsonResponse
    {
        $maxSerialNo = Proposal::max('serial_no') ?? 0;
        $nextSerialNo = $maxSerialNo + 1;

        return $this->success([
            'next_serial_no' => $nextSerialNo,
            'statuses'       => Proposal::STATUSES,
        ], 'Proposal form options retrieved successfully');
    }

    // ─── Export Endpoints (Excel, CSV, PDF) ───────────────────────────────────

    /**
     * Unified Export Endpoint (supports format=xlsx, format=csv, format=pdf).
     * GET /api/admin/crm/proposals/export
     */
    public function export(Request $request)
    {
        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }
        if ($format === 'csv') {
            return $this->exportCsv($request);
        }
        return $this->exportExcel($request);
    }

    /**
     * Export proposals to Excel (.xlsx).
     * GET /api/admin/crm/proposals/export/excel
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $rows = $this->prepareExportRows($this->buildFilteredQuery($request)->get());
        $filename = 'proposals_' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new ProposalExport($rows), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export proposals to CSV (.csv).
     * GET /api/admin/crm/proposals/export/csv
     */
    public function exportCsv(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $rows = $this->prepareExportRows($this->buildFilteredQuery($request)->get());
        $filename = 'proposals_' . now()->format('YmdHis') . '.csv';

        return Excel::download(new ProposalExport($rows), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Export proposals to PDF (.pdf).
     * GET /api/admin/crm/proposals/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $rows = $this->prepareExportRows($this->buildFilteredQuery($request)->get());
        $export = new ProposalExport($rows);
        $company = Company::first();
        $filename = 'proposals_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data'     => $export->array(),
            'headings' => $export->headings(),
            'title'    => 'Proposals Report',
            'company'  => $company,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Build a reusable filtered query (index + export share the same filters).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Proposal::query()->with(['customerRelation:id,company_name,contact_person_name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('date', '<=', $request->input('to_date'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('customer', 'like', "%{$search}%")
                  ->orWhere('proposal_reference_no', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $sortBy    = $request->input('sort_by', 'serial_no');
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowed   = ['serial_no', 'date', 'customer', 'proposal_reference_no', 'status', 'created_at'];

        if (in_array($sortBy, $allowed, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('serial_no', 'asc');
        }

        return $query;
    }

    /**
     * Prepare flat rows for CSV / Excel / PDF exports.
     */
    private function prepareExportRows($proposals): array
    {
        $rows = [];
        foreach ($proposals as $p) {
            $rows[] = [
                $p->serial_no ?? '-',
                $p->date ? (is_string($p->date) ? $p->date : $p->date->format('Y-m-d')) : '-',
                $p->customer ?? ($p->customerRelation->company_name ?? '-'),
                $p->proposal_reference_no ?? '-',
                $p->drive_link ?? '-',
                $p->status ?? '-',
                $p->created_at ? $p->created_at->format('Y-m-d H:i') : '-',
            ];
        }
        return $rows;
    }

    /**
     * Authenticate via bearer token or URL query parameter token (for direct browser downloads).
     */
    private function authenticateFromToken(Request $request): void
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Fail silently
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }
}
