<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Exports\QuotationExport;
use App\Mail\QuotationMail;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\ProductCurrency;
use App\Models\ProductTaxRate;
use App\Models\Quotation;
use App\Models\QuotationCurrency;
use App\Models\QuotationItem;
use App\Models\QuotationPaymentTerm;
use App\Models\QuotationTaxRate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class QuotationController extends ApiController
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Recalculate and persist totals for a quotation from its items.
     */
    private function recalcTotals(Quotation $quotation): void
    {
        $items = $quotation->items;

        $subtotal      = 0.00;
        $discountTotal = 0.00;
        $taxTotal      = 0.00;

        foreach ($items as $item) {
            $base = (float) $item->quantity * (float) $item->unit_price;

            if ($item->discount_type === 'percent') {
                $disc = $base * ((float) $item->discount / 100);
            } else {
                $disc = (float) $item->discount;
            }

            $afterDiscount = $base - $disc;
            $tax           = $afterDiscount * ((float) $item->tax_percent / 100);

            $subtotal      += $base;
            $discountTotal += $disc;
            $taxTotal      += $tax;
        }

        $quotation->subtotal      = round($subtotal, 2);
        $quotation->discount_total = round($discountTotal, 2);
        $quotation->tax_total     = round($taxTotal, 2);
        $quotation->grand_total   = round(
            $subtotal - $discountTotal + $taxTotal + (float) $quotation->additional_charges,
            2
        );
        $quotation->save();
    }

    /**
     * Sync line items for a quotation (delete all + re-insert).
     */
    private function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();

        foreach ($items as $idx => $row) {
            $qty          = (float) ($row['quantity'] ?? 1);
            $unitPrice    = (float) ($row['unit_price'] ?? 0);
            $discount     = (float) ($row['discount'] ?? 0);
            $discountType = $row['discount_type'] ?? 'percent';
            $taxPercent   = (float) ($row['tax_percent'] ?? 0);

            $lineTotal = QuotationItem::calculateLineTotal(
                $qty, $unitPrice, $discount, $discountType, $taxPercent
            );

            QuotationItem::create([
                'quotation_id'    => $quotation->id,
                'product_service' => $row['product_service'] ?? '',
                'description'     => $row['description'] ?? null,
                'quantity'        => $qty,
                'unit_price'      => $unitPrice,
                'discount'        => $discount,
                'discount_type'   => $discountType,
                'tax_percent'     => $taxPercent,
                'line_total'      => $lineTotal,
                'sort_order'      => $idx,
            ]);
        }
    }

    // ─── Listing ──────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations
     * List quotations with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::with(['customer:id,company_name,contact_person_name,contact_email,contact_phone'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        return $this->success($query->paginate($perPage));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    /**
     * POST /api/admin/crm/quotations
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'         => 'required|exists:customers,id',
            'contact_person'      => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'billing_address'     => 'nullable|string',
            'lead_id'             => 'nullable|exists:leads,id',
            'quotation_date'      => 'required|date',
            'valid_until'         => 'required|date|after_or_equal:quotation_date',
            'currency'            => 'nullable|string|max:10',
            'payment_terms'       => 'nullable|string|max:100',
            'delivery_timeline'   => 'nullable|string|max:255',
            'additional_charges'  => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'terms_and_conditions'=> 'nullable|string',
            'status'              => 'nullable|in:draft,sent,accepted,rejected,expired,cancelled',
            'items'               => 'required|array|min:1',
            'items.*.product_service' => 'required|string|max:255',
            'items.*.description'     => 'nullable|string',
            'items.*.quantity'        => 'required|numeric|min:0.01',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount'        => 'nullable|numeric|min:0',
            'items.*.discount_type'   => 'nullable|in:percent,fixed',
            'items.*.tax_percent'     => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            $quotation = Quotation::create([
                'customer_id'          => $data['customer_id'],
                'contact_person'       => $data['contact_person'] ?? null,
                'contact_email'        => $data['contact_email'] ?? null,
                'contact_phone'        => $data['contact_phone'] ?? null,
                'billing_address'      => $data['billing_address'] ?? null,
                'lead_id'              => $data['lead_id'] ?? null,
                'quotation_date'       => $data['quotation_date'],
                'valid_until'          => $data['valid_until'],
                'currency'             => $data['currency'] ?? 'INR',
                'payment_terms'        => $data['payment_terms'] ?? null,
                'delivery_timeline'    => $data['delivery_timeline'] ?? null,
                'additional_charges'   => $data['additional_charges'] ?? 0,
                'notes'                => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'status'               => $data['status'] ?? 'draft',
                'created_by'           => Auth::id(),
            ]);

            $this->syncItems($quotation, $data['items']);
            $this->recalcTotals($quotation);

            DB::commit();

            return $this->success(
                $quotation->load('items', 'customer'),
                'Quotation created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create quotation: ' . $e->getMessage(), 500);
        }
    }

    // ─── Read ─────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations/{quotation}
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $quotation->load(['customer', 'lead:id,lead_name,lead_id', 'items', 'creator:id', 'updater:id']);
        return $this->success($quotation);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    /**
     * PUT /api/admin/crm/quotations/{quotation}
     */
    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'         => 'sometimes|exists:customers,id',
            'contact_person'      => 'nullable|string|max:255',
            'contact_email'       => 'nullable|email|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'billing_address'     => 'nullable|string',
            'lead_id'             => 'nullable|exists:leads,id',
            'quotation_date'      => 'sometimes|date',
            'valid_until'         => 'sometimes|date',
            'currency'            => 'nullable|string|max:10',
            'payment_terms'       => 'nullable|string|max:100',
            'delivery_timeline'   => 'nullable|string|max:255',
            'additional_charges'  => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'terms_and_conditions'=> 'nullable|string',
            'status'              => 'nullable|in:draft,sent,accepted,rejected,expired,cancelled',
            'items'               => 'sometimes|array|min:1',
            'items.*.product_service' => 'required_with:items|string|max:255',
            'items.*.description'     => 'nullable|string',
            'items.*.quantity'        => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'      => 'required_with:items|numeric|min:0',
            'items.*.discount'        => 'nullable|numeric|min:0',
            'items.*.discount_type'   => 'nullable|in:percent,fixed',
            'items.*.tax_percent'     => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            $quotation->fill(array_merge(
                Arr::except($data, ['items']),
                ['updated_by' => Auth::id()]
            ));
            $quotation->save();

            if (isset($data['items'])) {
                $this->syncItems($quotation, $data['items']);
            }

            $this->recalcTotals($quotation);

            DB::commit();

            return $this->success(
                $quotation->load('items', 'customer'),
                'Quotation updated successfully'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update quotation: ' . $e->getMessage(), 500);
        }
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    /**
     * DELETE /api/admin/crm/quotations/{quotation}
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->update(['deleted_by' => Auth::id()]);
        $quotation->delete();

        return $this->success(null, 'Quotation deleted successfully');
    }

    // ─── Status Update ────────────────────────────────────────────────────────

    /**
     * POST /api/admin/crm/quotations/{quotation}/update-status
     */
    public function updateStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,sent,accepted,rejected,expired,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $quotation->update([
            'status'     => $request->status,
            'updated_by' => Auth::id(),
        ]);

        return $this->success($quotation->fresh(), 'Status updated successfully');
    }

    // ─── Form Options ─────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations/form-options
     * Returns dropdown data needed to build the Add/Edit quotation form.
     */
    public function formOptions(): JsonResponse
    {
        // Active customers
        $customers = Customer::select('id', 'customer_id', 'company_name', 'contact_person_name', 'contact_email', 'contact_phone', 'billing_address', 'payment_terms')
            ->where('customer_status', 'Active')
            ->orderBy('company_name')
            ->get()
            ->map(fn ($c) => [
                'id'             => $c->id,
                'customer_id'    => $c->customer_id,
                'company_name'   => $c->company_name,
                'contact_person' => $c->contact_person_name,
                'email'          => $c->contact_email,
                'phone'          => $c->contact_phone,
                'billing_address'=> $c->billing_address,
                'payment_terms'  => $c->payment_terms,
            ]);

        // Open leads
        $leads = Lead::select('id', 'lead_id', 'lead_name', 'company_name')
            ->whereNotIn('lead_status', ['Closed', 'Lost'])
            ->orderBy('lead_name')
            ->get()
            ->map(fn ($l) => [
                'id'           => $l->id,
                'lead_id'      => $l->lead_id,
                'label'        => $l->lead_name . ($l->company_name ? ' – ' . $l->company_name : ''),
            ]);

        // Open Opportunities
        $opportunities = Opportunity::select('id', 'opportunity_id', 'name', 'customer_id', 'estimated_value', 'currency')
            ->open()
            ->orderBy('name')
            ->get()
            ->map(fn ($o) => [
                'id'              => $o->id,
                'opportunity_id'  => $o->opportunity_id,
                'name'            => $o->name,
                'label'           => ($o->opportunity_id ? $o->opportunity_id . ' – ' : '') . $o->name,
                'customer_id'     => $o->customer_id,
                'estimated_value' => $o->estimated_value,
                'currency'        => $o->currency,
            ]);

        $year   = now()->year;
        $nextId = (Quotation::withTrashed()->max('id') ?? 0) + 1;
        $nextQuotationNumber = 'Q-' . $year . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        // Currencies: Check QuotationCurrency first, fallback to ProductCurrency / default array
        try {
            $currencyNames = QuotationCurrency::orderBy('id')->pluck('name')->toArray();
            if (!empty($currencyNames)) {
                $currencies = $currencyNames;
            } else {
                $currencyList = ProductCurrency::orderBy('id')->get(['code', 'symbol', 'name', 'is_default'])->toArray();
                $currencies = !empty($currencyList) ? array_column($currencyList, 'code') : QuotationCurrency::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $currencies = QuotationCurrency::DEFAULTS;
        }

        // Payment Terms: Check QuotationPaymentTerm first, fallback to default array
        try {
            $paymentTerms = QuotationPaymentTerm::orderBy('id')->pluck('name')->toArray();
            if (empty($paymentTerms)) {
                $paymentTerms = QuotationPaymentTerm::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $paymentTerms = QuotationPaymentTerm::DEFAULTS;
        }

        // Taxes: Check QuotationTaxRate first, fallback to ProductTaxRate / defaults
        $taxes = [];
        try {
            $taxes = QuotationTaxRate::orderBy('id')->get(['id', 'name', 'rate', 'is_default'])->toArray();
            if (empty($taxes)) {
                $taxes = ProductTaxRate::orderBy('id')->get(['id', 'name', 'rate', 'is_default'])->toArray();
            }
            if (empty($taxes)) {
                $taxes = QuotationTaxRate::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $taxes = QuotationTaxRate::DEFAULTS;
        }

        return $this->success([
            'next_quotation_number' => $nextQuotationNumber,
            'customers'             => $customers,
            'leads'                 => $leads,
            'opportunities'         => $opportunities,
            'currencies'            => $currencies,
            'payment_terms'         => $paymentTerms,
            'taxes'                 => $taxes,
            'statuses'              => ['draft', 'sent', 'accepted', 'rejected', 'expired', 'cancelled'],
            'discount_types'        => ['percent', 'fixed'],
        ]);
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations/export
     * Supports format=xlsx, format=csv, format=pdf
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $query = Quotation::with('customer')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $quotations = $query->get();

        $rows = [];
        foreach ($quotations as $q) {
            $rows[] = [
                $q->quotation_number ?? '',
                optional($q->customer)->company_name ?? '',
                $q->status ?? '',
                $q->quotation_date ? \Carbon\Carbon::parse($q->quotation_date)->format('Y-m-d') : '',
                $q->valid_until ? \Carbon\Carbon::parse($q->valid_until)->format('Y-m-d') : '',
                $q->currency ?? '',
                $q->subtotal ?? 0,
                $q->discount_total ?? 0,
                $q->tax_total ?? 0,
                $q->grand_total ?? 0,
            ];
        }

        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'quotations_' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            $export = new QuotationExport($rows);
            $pdf = Pdf::loadView('reports.generic_pdf', [
                'data'     => $export->array(),
                'headings' => $export->headings(),
                'title'    => 'Quotations Report',
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        if ($format === 'csv') {
            return Excel::download(new QuotationExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new QuotationExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    // ─── Single Quotation PDF ────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations/{quotation}/pdf
     * Download or view individual quotation PDF.
     */
    public function downloadPdf(Request $request, Quotation $quotation): \Symfony\Component\HttpFoundation\Response
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $quotation->load(['customer', 'items', 'lead']);
        $company = Company::first();

        $pdf = Pdf::loadView('pdf.quotation', [
            'quotation' => $quotation,
            'company'   => $company,
        ])->setPaper('a4', 'portrait');

        $filename = 'Quotation_' . ($quotation->quotation_number ?? $quotation->id) . '.pdf';

        if ($request->boolean('preview') || $request->input('disposition') === 'inline') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    // ─── Email Management ───────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotations/{quotation}/email-preview
     * Pre-populate email fields for the modal.
     */
    public function emailPreview(Quotation $quotation): JsonResponse
    {
        $quotation->load(['customer', 'items']);
        $customerName = $quotation->customer?->company_name
            ?? $quotation->customer?->contact_person_name
            ?? $quotation->contact_person
            ?? 'Customer';

        $quoteNumber = $quotation->quotation_number ?? ('#' . $quotation->id);
        $dateFormatted = $quotation->quotation_date
            ? \Carbon\Carbon::parse($quotation->quotation_date)->format('d/m/Y')
            : date('d/m/Y');
        $validUntilFormatted = $quotation->valid_until
            ? \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y')
            : '';

        $toEmail = $quotation->contact_email
            ?: ($quotation->customer?->contact_email ?? '');

        $subject = "Quotation {$quoteNumber} from Mostech";

        $message = "Dear {$customerName} team,\n\n"
            . "Please find attached our quotation {$quoteNumber} dated {$dateFormatted}.\n\n"
            . ($validUntilFormatted ? "The quotation is valid until {$validUntilFormatted}.\n\n" : '')
            . "Kindly review and revert at your earliest convenience.";

        return $this->success([
            'to'               => $toEmail,
            'cc'               => '',
            'subject'          => $subject,
            'message'          => $message,
            'attach_pdf'       => true,
            'quotation_number' => $quoteNumber,
            'customer_name'    => $customerName,
            'quotation_date'   => $dateFormatted,
            'valid_until'      => $validUntilFormatted,
        ]);
    }

    /**
     * POST /api/admin/crm/quotations/{quotation}/send-email
     * Send quotation email with optional PDF attachment.
     */
    public function sendEmail(Request $request, Quotation $quotation): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to'         => 'required',
            'cc'         => 'nullable',
            'subject'    => 'required|string|max:255',
            'message'    => 'required|string',
            'attach_pdf' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $toEmails = $this->normalizeEmailList($request->input('to'));
        $ccEmails = $this->normalizeEmailList($request->input('cc'));

        if (empty($toEmails)) {
            return $this->error('Please provide at least one valid recipient email address (TO).', 422);
        }

        $quotation->load(['customer', 'items', 'lead']);
        $company = Company::first();

        $attachPdf   = filter_var($request->input('attach_pdf', true), FILTER_VALIDATE_BOOLEAN);
        $pdfContent  = null;
        $pdfFilename = 'Quotation_' . ($quotation->quotation_number ?? $quotation->id) . '.pdf';

        if ($attachPdf) {
            $pdf = Pdf::loadView('pdf.quotation', [
                'quotation' => $quotation,
                'company'   => $company,
            ])->setPaper('a4', 'portrait');

            $pdfContent = $pdf->output();
        }

        try {
            $mail = Mail::to($toEmails);
            if (!empty($ccEmails)) {
                $mail->cc($ccEmails);
            }

            $mailable = new QuotationMail(
                $quotation,
                $request->input('subject'),
                $request->input('message'),
                $pdfContent,
                $pdfFilename
            );

            $mail->send($mailable);

            // Update status to 'sent' if it was 'draft'
            if ($quotation->status === 'draft') {
                $quotation->status = 'sent';
            }
            $quotation->updated_by = Auth::id();
            $quotation->save();

            // Log CRM Activity
            try {
                Activity::create([
                    'title'                => 'Quotation ' . ($quotation->quotation_number ?? $quotation->id) . ' emailed to ' . implode(', ', $toEmails),
                    'activity_type'        => 'Email',
                    'priority'             => 'Medium',
                    'related_record_type'  => 'Quotation',
                    'related_record_id'    => $quotation->id,
                    'assigned_employee_id' => Auth::id(),
                    'status'               => 'Completed',
                    'start_date_time'      => now(),
                    'end_date_time'        => now(),
                    'description'          => "Quotation email sent.\nTo: " . implode(', ', $toEmails) .
                        (!empty($ccEmails) ? "\nCC: " . implode(', ', $ccEmails) : '') .
                        "\nSubject: {$request->input('subject')}\n\nMessage:\n{$request->input('message')}" .
                        ($attachPdf ? "\n\nAttachment: {$pdfFilename}" : ''),
                ]);
            } catch (\Throwable $actEx) {
                // Non-critical if activity logging encounters schema mismatch
            }

            return $this->success(
                $quotation->fresh()->load('customer', 'items'),
                'Quotation email sent successfully'
            );
        } catch (\Throwable $e) {
            return $this->error('Failed to send quotation email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Parse and filter email list from string or array.
     */
    private function normalizeEmailList($emails): array
    {
        if (empty($emails)) {
            return [];
        }

        if (is_string($emails)) {
            $emails = preg_split('/[,;\s]+/', $emails);
        }

        if (!is_array($emails)) {
            return [];
        }

        $valid = [];
        foreach ($emails as $email) {
            $cleaned = trim((string) $email);
            if (!empty($cleaned) && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $valid[] = $cleaned;
            }
        }

        return array_values(array_unique($valid));
    }
}

