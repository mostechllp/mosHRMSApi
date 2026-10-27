<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\QuotationCurrency;
use App\Models\QuotationPaymentTerm;
use App\Models\QuotationTaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Quotation Settings API Controller
 *
 * Manages the three quotation configuration panels:
 *   - Currencies    (AED, USD, INR, etc. - id, name, timestamps, soft deletes)
 *   - Payment Terms (Net 15, Net 30, Net 35, Net 45, Net 60, etc. - id, name, timestamps, soft deletes)
 *   - Taxes         (GST 18%, GST 12%, GST 5%, Exempt - id, name, rate, is_default, timestamps, soft deletes)
 *
 * All endpoints are prefixed with /api/admin/crm/quotation-settings/
 */
class QuotationSettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET & SEED
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all quotation setting groups in one call.
     * GET /api/admin/crm/quotation-settings
     */
    public function index(): JsonResponse
    {
        [$currencies, $paymentTerms, $taxes] = $this->fetchAll();

        return $this->success([
            'currencies' => $currencies,
            'payment_terms' => $paymentTerms,
            'taxes' => $taxes,
        ], 'Quotation settings retrieved successfully');
    }

    /**
     * Seed default values for all three groups (only inserts missing ones).
     * POST /api/admin/crm/quotation-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Currencies
            foreach (QuotationCurrency::DEFAULTS as $name) {
                QuotationCurrency::firstOrCreate(['name' => $name]);
            }

            // Payment Terms
            foreach (QuotationPaymentTerm::DEFAULTS as $name) {
                QuotationPaymentTerm::firstOrCreate(['name' => $name]);
            }

            // Taxes
            foreach (QuotationTaxRate::DEFAULTS as $tax) {
                QuotationTaxRate::firstOrCreate(
                    ['name' => $tax['name']],
                    ['rate' => $tax['rate'], 'is_default' => $tax['is_default']]
                );
            }

            DB::commit();

            [$currencies, $paymentTerms, $taxes] = $this->fetchAll();

            return $this->success([
                'currencies' => $currencies,
                'payment_terms' => $paymentTerms,
                'taxes' => $taxes,
            ], 'Default quotation settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CURRENCIES (AED, USD, etc. - only name)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotation-settings/currencies
     */
    public function indexCurrencies(): JsonResponse
    {
        try {
            $currencies = QuotationCurrency::orderBy('id')->get();
            return $this->success($currencies, 'Quotation currencies retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve quotation currencies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/quotation-settings/currencies
     */
    public function storeCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:quotation_currencies,name',
        ]);

        try {
            $currency = QuotationCurrency::create($validated);
            return $this->success($currency, 'Quotation currency created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create quotation currency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/quotation-settings/currencies/{id}
     */
    public function showCurrency($id): JsonResponse
    {
        $currency = QuotationCurrency::find($id);
        if (!$currency) {
            return $this->error('Quotation currency not found', 404);
        }
        return $this->success($currency, 'Quotation currency retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/quotation-settings/currencies/{id}
     */
    public function updateCurrency(Request $request, $id): JsonResponse
    {
        $currency = QuotationCurrency::find($id);
        if (!$currency) {
            return $this->error('Quotation currency not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:quotation_currencies,name,' . $id,
        ]);

        try {
            $currency->update($validated);
            return $this->success($currency->fresh(), 'Quotation currency updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update quotation currency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/quotation-settings/currencies/{id}
     */
    public function destroyCurrency($id): JsonResponse
    {
        $currency = QuotationCurrency::find($id);
        if (!$currency) {
            return $this->error('Quotation currency not found', 404);
        }

        try {
            $currency->delete();
            return $this->success(null, 'Quotation currency deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete quotation currency: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PAYMENT TERMS (Net 30, Net 35, etc. - only name)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotation-settings/payment-terms
     */
    public function indexPaymentTerms(): JsonResponse
    {
        try {
            $terms = QuotationPaymentTerm::orderBy('id')->get();
            return $this->success($terms, 'Quotation payment terms retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve quotation payment terms: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/quotation-settings/payment-terms
     */
    public function storePaymentTerm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:quotation_payment_terms,name',
        ]);

        try {
            $term = QuotationPaymentTerm::create($validated);
            return $this->success($term, 'Quotation payment term created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create quotation payment term: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/quotation-settings/payment-terms/{id}
     */
    public function showPaymentTerm($id): JsonResponse
    {
        $term = QuotationPaymentTerm::find($id);
        if (!$term) {
            return $this->error('Quotation payment term not found', 404);
        }
        return $this->success($term, 'Quotation payment term retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/quotation-settings/payment-terms/{id}
     */
    public function updatePaymentTerm(Request $request, $id): JsonResponse
    {
        $term = QuotationPaymentTerm::find($id);
        if (!$term) {
            return $this->error('Quotation payment term not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:quotation_payment_terms,name,' . $id,
        ]);

        try {
            $term->update($validated);
            return $this->success($term->fresh(), 'Quotation payment term updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update quotation payment term: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/quotation-settings/payment-terms/{id}
     */
    public function destroyPaymentTerm($id): JsonResponse
    {
        $term = QuotationPaymentTerm::find($id);
        if (!$term) {
            return $this->error('Quotation payment term not found', 404);
        }

        try {
            $term->delete();
            return $this->success(null, 'Quotation payment term deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete quotation payment term: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  TAXES (GST 18%, GST 12%, GST 5%, Exempt - name, rate, is_default)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/quotation-settings/taxes
     */
    public function indexTaxes(): JsonResponse
    {
        try {
            $taxes = QuotationTaxRate::orderBy('id')->get();
            return $this->success($taxes, 'Quotation taxes retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve quotation taxes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/quotation-settings/taxes
     */
    public function storeTax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);

        try {
            DB::beginTransaction();

            if ($validated['is_default']) {
                QuotationTaxRate::where('is_default', true)->update(['is_default' => false]);
            }

            $tax = QuotationTaxRate::create($validated);

            DB::commit();
            return $this->success($tax, 'Quotation tax rate created successfully', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create quotation tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/quotation-settings/taxes/{id}
     */
    public function showTax($id): JsonResponse
    {
        $tax = QuotationTaxRate::find($id);
        if (!$tax) {
            return $this->error('Quotation tax rate not found', 404);
        }
        return $this->success($tax, 'Quotation tax rate retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/quotation-settings/taxes/{id}
     */
    public function updateTax(Request $request, $id): JsonResponse
    {
        $tax = QuotationTaxRate::find($id);
        if (!$tax) {
            return $this->error('Quotation tax rate not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            if (isset($validated['is_default']) && $validated['is_default']) {
                QuotationTaxRate::where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
            }

            $tax->update($validated);

            DB::commit();
            return $this->success($tax->fresh(), 'Quotation tax rate updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update quotation tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/admin/crm/quotation-settings/taxes/{id}/set-default
     */
    public function setDefaultTax($id): JsonResponse
    {
        $tax = QuotationTaxRate::find($id);
        if (!$tax) {
            return $this->error('Quotation tax rate not found', 404);
        }

        try {
            DB::beginTransaction();
            QuotationTaxRate::where('is_default', true)->update(['is_default' => false]);
            $tax->update(['is_default' => true]);
            DB::commit();

            return $this->success($tax->fresh(), 'Default quotation tax rate set successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to set default tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/quotation-settings/taxes/{id}
     */
    public function destroyTax($id): JsonResponse
    {
        $tax = QuotationTaxRate::find($id);
        if (!$tax) {
            return $this->error('Quotation tax rate not found', 404);
        }

        try {
            $tax->delete();
            return $this->success(null, 'Quotation tax rate deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete quotation tax rate: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {
        $currencies = QuotationCurrency::orderBy('id')->get();
        $paymentTerms = QuotationPaymentTerm::orderBy('id')->get();
        $taxes = QuotationTaxRate::orderBy('id')->get();

        return [$currencies, $paymentTerms, $taxes];
    }

    private function defaultCurrencies(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], QuotationCurrency::DEFAULTS, array_keys(QuotationCurrency::DEFAULTS));
    }

    private function defaultPaymentTerms(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], QuotationPaymentTerm::DEFAULTS, array_keys(QuotationPaymentTerm::DEFAULTS));
    }

    private function defaultTaxes(): array
    {
        return array_map(fn($t, $i) => [
            'id' => $i + 1,
            'name' => $t['name'],
            'rate' => $t['rate'],
            'is_default' => $t['is_default'],
        ], QuotationTaxRate::DEFAULTS, array_keys(QuotationTaxRate::DEFAULTS));
    }
}
