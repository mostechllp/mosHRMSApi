<?php

namespace App\Http\Controllers\Api\CRM;

use App\Http\Controllers\Api\ApiController;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadWebhookApiController extends ApiController
{
    /**
     * Verify Meta Webhook handshake (GET /api/crm/webhooks/meta).
     * Used by Facebook Lead Ads, Instagram Lead Ads, and WhatsApp Cloud API.
     */
    public function verifyMetaWebhook(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('services.crm_leads.meta.verify_token', env('META_WEBHOOK_VERIFY_TOKEN', 'crm_meta_lead_verify_token'));

        if ($mode === 'subscribe' && !empty($token) && $token === $expectedToken) {
            Log::info('Meta Webhook verified successfully');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Meta Webhook verification failed. Received token: ' . $token);
        return response('Forbidden: Verification token mismatch', 403);
    }

    /**
     * Handle incoming Meta Webhook events (POST /api/crm/webhooks/meta).
     * Dispatches Facebook Lead Ads, Instagram Lead Ads, and WhatsApp messages.
     */
    public function handleMetaWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::info('Meta Webhook received payload: ', ['payload' => $payload]);

        $object = $payload['object'] ?? '';

        try {
            // 1. WhatsApp Cloud API events
            if ($object === 'whatsapp_business_account') {
                $this->processWhatsAppWebhook($payload);
                return response()->json(['status' => 'EVENT_RECEIVED'], 200);
            }

            // 2. Facebook & Instagram Lead Ads events (object: page)
            if ($object === 'page' || isset($payload['entry'])) {
                $entries = $payload['entry'] ?? [];
                foreach ($entries as $entry) {
                    $changes = $entry['changes'] ?? [];
                    foreach ($changes as $change) {
                        $field = $change['field'] ?? '';
                        $value = $change['value'] ?? [];

                        if ($field === 'leadgen') {
                            $this->processMetaLeadgen($value);
                        }
                    }
                }

                return response()->json(['status' => 'EVENT_RECEIVED'], 200);
            }
        } catch (\Throwable $e) {
            Log::error('Error processing Meta Webhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Meta requires 200 OK fast response to acknowledge receipt
        return response()->json(['status' => 'EVENT_RECEIVED'], 200);
    }

    /**
     * Universal Secure Lead Ingest API / Webhook (POST /api/crm/leads/webhook & /api/crm/leads/ingest).
     * Accepts leads from Zapier, Make.com, LinkedIn Lead Gen, WhatsApp providers, landing pages, etc.
     */
    public function ingestLead(Request $request): JsonResponse
    {
        // 1. Verify Secret Token / API Key
        $configuredSecret = config('services.crm_leads.webhook_secret', env('CRM_LEAD_WEBHOOK_SECRET'));
        if (!empty($configuredSecret)) {
            $providedToken = $request->header('X-Lead-Token')
                ?? $request->header('X-CRM-Key')
                ?? $request->bearerToken()
                ?? $request->input('api_key')
                ?? $request->input('token');

            if ($providedToken !== $configuredSecret) {
                return $this->error('Unauthorized: Invalid or missing lead webhook token', 401);
            }
        }

        // 2. Normalize Incoming Data
        $data = $this->normalizeLeadPayload($request->all());

        if (empty($data['lead_name'])) {
            return $this->error('Lead name is required (pass lead_name, name, full_name, or first_name)', 422);
        }

        DB::beginTransaction();
        try {
            // Check for existing lead by email or primary phone to prevent duplicate spam
            $existingLead = null;
            if (!empty($data['email'])) {
                $existingLead = Lead::where('email', $data['email'])->first();
            }
            if (!$existingLead && !empty($data['primary_phone'])) {
                $existingLead = Lead::where('primary_phone', $data['primary_phone'])->first();
            }

            if ($existingLead) {
                // Append note regarding recent submission
                $noteUpdate = "\n[" . now()->format('Y-m-d H:i') . "] Re-submitted via " . ($data['lead_source'] ?? 'Webhook');
                if (!empty($data['notes'])) {
                    $noteUpdate .= ": " . $data['notes'];
                }
                $existingLead->notes = ($existingLead->notes ?? '') . $noteUpdate;
                $existingLead->save();

                DB::commit();

                return $this->success([
                    'lead' => $existingLead->load(['assignedSalesperson']),
                    'is_duplicate' => true,
                    'message' => 'Existing lead updated with new submission info',
                ], 'Lead already exists, information appended', 200);
            }

            // Set salesperson assignment (from request, or default config, or null)
            $salespersonId = $data['assigned_salesperson_id']
                ?? config('services.crm_leads.default_salesperson_id')
                ?? null;

            $lead = Lead::create([
                'lead_type' => $data['lead_type'] ?? 'Company',
                'lead_name' => $data['lead_name'],
                'company_name' => $data['company_name'] ?? null,
                'designation' => $data['designation'] ?? null,
                'industry' => $data['industry'] ?? null,
                'website' => $data['website'] ?? null,
                'lead_source' => $data['lead_source'] ?? 'Website',
                'lead_status' => $data['lead_status'] ?? 'New',
                'priority' => $data['priority'] ?? 'Medium',
                'email' => $data['email'] ?? null,
                'primary_phone' => $data['primary_phone'] ?? null,
                'alternate_phone' => $data['alternate_phone'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? 'India',
                'assigned_salesperson_id' => $salespersonId,
                'sales_team' => $data['sales_team'] ?? null,
                'expected_value' => $data['expected_value'] ?? 0.00,
                'expected_closing_date' => $data['expected_closing_date'] ?? null,
                'interested_products' => $data['interested_products'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            DB::commit();

            return $this->success(
                $lead->load(['assignedSalesperson']),
                'Lead ingested and added to CRM successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Lead ingestion failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);
            return $this->error('Failed to ingest lead: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Process Meta Lead Ads submission (Facebook & Instagram).
     */
    protected function processMetaLeadgen(array $value): void
    {
        $leadgenId = $value['leadgen_id'] ?? null;
        $pageId = $value['page_id'] ?? null;
        $formId = $value['form_id'] ?? null;
        $adId = $value['ad_id'] ?? null;

        if (!$leadgenId) {
            return;
        }

        $pageAccessToken = config('services.crm_leads.meta.page_access_token', env('META_PAGE_ACCESS_TOKEN'));
        $graphVersion = config('services.crm_leads.meta.graph_version', 'v19.0');

        $leadDetails = null;

        if (!empty($pageAccessToken)) {
            try {
                $response = Http::get("https://graph.facebook.com/{$graphVersion}/{$leadgenId}", [
                    'access_token' => $pageAccessToken,
                ]);

                if ($response->successful()) {
                    $leadDetails = $response->json();
                } else {
                    Log::warning("Meta Graph API error for leadgen_id {$leadgenId}: " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::error("Failed to query Meta Graph API: " . $e->getMessage());
            }
        }

        // Map fields from Graph API response
        $parsed = $this->parseMetaFieldData($leadDetails, $value);

        $source = $parsed['lead_source'] ?? 'Facebook';
        $leadName = $parsed['lead_name'] ?? ('Meta Lead #' . $leadgenId);

        $salespersonId = config('services.crm_leads.default_salesperson_id');

        Lead::create([
            'lead_type' => 'Company',
            'lead_name' => $leadName,
            'company_name' => $parsed['company_name'] ?? null,
            'designation' => $parsed['designation'] ?? null,
            'lead_source' => $source,
            'lead_status' => 'New',
            'priority' => 'Medium',
            'email' => $parsed['email'] ?? null,
            'primary_phone' => $parsed['primary_phone'] ?? null,
            'city' => $parsed['city'] ?? null,
            'state' => $parsed['state'] ?? null,
            'country' => $parsed['country'] ?? 'India',
            'assigned_salesperson_id' => $salespersonId,
            'notes' => $parsed['notes'] ?? ("Captured via Meta Lead Ad Form: {$formId}, Ad: {$adId}"),
        ]);
    }

    /**
     * Process WhatsApp Webhook payload (WhatsApp Cloud API).
     */
    protected function processWhatsAppWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $val = $change['value'] ?? [];
                $contacts = $val['contacts'] ?? [];
                $messages = $val['messages'] ?? [];

                $contactName = $contacts[0]['profile']['name'] ?? null;
                $waId = $contacts[0]['wa_id'] ?? null;

                foreach ($messages as $msg) {
                    $from = $msg['from'] ?? $waId;
                    $textBody = $msg['text']['body'] ?? ($msg['type'] ?? 'WhatsApp Message');

                    if (empty($from)) {
                        continue;
                    }

                    // Check if a lead with this WhatsApp number exists
                    $existingLead = Lead::where('primary_phone', 'like', "%{$from}%")->first();

                    if ($existingLead) {
                        // Append activity/follow-up note
                        LeadFollowUp::create([
                            'lead_id' => $existingLead->id,
                            'follow_up_type' => 'WhatsApp',
                            'follow_up_date' => now(),
                            'status' => 'completed',
                            'notes' => "Inbound WhatsApp message: " . $textBody,
                        ]);
                    } else {
                        // Create new lead
                        $leadName = $contactName ?: ('WhatsApp Lead (' . $from . ')');
                        $salespersonId = config('services.crm_leads.default_salesperson_id');

                        Lead::create([
                            'lead_type' => 'Individual',
                            'lead_name' => $leadName,
                            'lead_source' => 'WhatsApp',
                            'lead_status' => 'New',
                            'priority' => 'Medium',
                            'primary_phone' => $from,
                            'assigned_salesperson_id' => $salespersonId,
                            'notes' => "Initial WhatsApp Inquiry: " . $textBody,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Parse Meta field_data array into normalized attributes.
     */
    protected function parseMetaFieldData(?array $metaData, array $rawChangeValue): array
    {
        $result = [
            'lead_source' => 'Facebook',
            'notes' => '',
        ];

        if (!$metaData || empty($metaData['field_data'])) {
            $result['notes'] = "Captured via Meta Leadgen ID: " . ($rawChangeValue['leadgen_id'] ?? 'N/A') .
                "\nForm ID: " . ($rawChangeValue['form_id'] ?? 'N/A') .
                "\nAd ID: " . ($rawChangeValue['ad_id'] ?? 'N/A');
            return $result;
        }

        $allQA = [];

        foreach ($metaData['field_data'] as $field) {
            $name = strtolower(trim($field['name'] ?? ''));
            $val = $field['values'][0] ?? null;

            if (empty($val)) {
                continue;
            }

            if (in_array($name, ['full_name', 'name', 'first_name'])) {
                $result['lead_name'] = $val;
            } elseif ($name === 'last_name' && !empty($result['lead_name'])) {
                $result['lead_name'] .= ' ' . $val;
            } elseif (in_array($name, ['email', 'work_email', 'contact_email'])) {
                $result['email'] = $val;
            } elseif (in_array($name, ['phone_number', 'phone', 'mobile'])) {
                $result['primary_phone'] = $val;
            } elseif (in_array($name, ['company_name', 'company', 'organization_name'])) {
                $result['company_name'] = $val;
            } elseif (in_array($name, ['job_title', 'designation', 'title'])) {
                $result['designation'] = $val;
            } elseif ($name === 'city') {
                $result['city'] = $val;
            } elseif ($name === 'state') {
                $result['state'] = $val;
            } elseif ($name === 'country') {
                $result['country'] = $val;
            } else {
                $allQA[] = "{$name}: {$val}";
            }
        }

        // Detect if Instagram was the platform
        if (isset($metaData['platform']) && stripos($metaData['platform'], 'instagram') !== false) {
            $result['lead_source'] = 'Instagram';
        }

        $notesExtra = !empty($allQA) ? ("Form Answers:\n" . implode("\n", $allQA)) : '';
        $metaDetails = "Meta Form ID: " . ($metaData['form_id'] ?? 'N/A') . " | Ad ID: " . ($metaData['ad_id'] ?? 'N/A');
        $result['notes'] = trim($metaDetails . "\n" . $notesExtra);

        return $result;
    }

    /**
     * Normalize flexible input payloads from Zapier, Make, LinkedIn, WhatsApp, etc.
     */
    protected function normalizeLeadPayload(array $raw): array
    {
        $normalized = [];

        // Lead Name
        $name = $raw['lead_name']
            ?? $raw['name']
            ?? $raw['full_name']
            ?? null;

        if (!$name && (!empty($raw['first_name']) || !empty($raw['last_name']))) {
            $name = trim(($raw['first_name'] ?? '') . ' ' . ($raw['last_name'] ?? ''));
        }

        if (!$name) {
            $name = $raw['company_name']
                ?? $raw['company']
                ?? (!empty($raw['email']) ? explode('@', $raw['email'])[0] : null)
                ?? (!empty($raw['phone']) || !empty($raw['primary_phone']) ? ('Lead (' . ($raw['phone'] ?? $raw['primary_phone']) . ')') : null);
        }

        $normalized['lead_name'] = $name;

        // Email
        $normalized['email'] = $raw['email'] ?? $raw['email_address'] ?? $raw['work_email'] ?? null;

        // Phone
        $normalized['primary_phone'] = $raw['primary_phone']
            ?? $raw['phone']
            ?? $raw['phone_number']
            ?? $raw['mobile']
            ?? $raw['wa_id']
            ?? null;

        $normalized['alternate_phone'] = $raw['alternate_phone'] ?? $raw['secondary_phone'] ?? null;

        // Company & Designation
        $normalized['company_name'] = $raw['company_name'] ?? $raw['company'] ?? $raw['organization'] ?? null;
        $normalized['designation'] = $raw['designation'] ?? $raw['job_title'] ?? $raw['title'] ?? null;
        $normalized['industry'] = $raw['industry'] ?? null;
        $normalized['website'] = $raw['website'] ?? $raw['site'] ?? null;

        // Source Normalization (Facebook, Instagram, LinkedIn, WhatsApp, etc.)
        $sourceRaw = $raw['lead_source']
            ?? $raw['source']
            ?? $raw['platform']
            ?? $raw['utm_source']
            ?? 'Website';

        $normalized['lead_source'] = $this->normalizeSourceName($sourceRaw);

        // Status & Priority
        $normalized['lead_status'] = $raw['lead_status'] ?? $raw['status'] ?? 'New';
        $normalized['priority'] = $raw['priority'] ?? 'Medium';
        $normalized['lead_type'] = $raw['lead_type'] ?? 'Company';

        // Address & Geography
        $normalized['address'] = $raw['address'] ?? null;
        $normalized['city'] = $raw['city'] ?? null;
        $normalized['state'] = $raw['state'] ?? null;
        $normalized['postal_code'] = $raw['postal_code'] ?? $raw['zip'] ?? null;
        $normalized['country'] = $raw['country'] ?? 'India';

        // Commercial details
        $normalized['sales_team'] = $raw['sales_team'] ?? null;
        $normalized['expected_value'] = isset($raw['expected_value']) ? (float) $raw['expected_value'] : 0.00;
        $normalized['expected_closing_date'] = $raw['expected_closing_date'] ?? null;

        // Interested Products (array or comma-separated string)
        if (isset($raw['interested_products'])) {
            if (is_array($raw['interested_products'])) {
                $normalized['interested_products'] = $raw['interested_products'];
            } elseif (is_string($raw['interested_products'])) {
                $normalized['interested_products'] = array_map('trim', explode(',', $raw['interested_products']));
            }
        }

        // Notes / Message
        $notes = $raw['notes'] ?? $raw['message'] ?? $raw['comments'] ?? $raw['inquiry'] ?? '';
        if (!empty($raw['ad_name']) || !empty($raw['campaign_name'])) {
            $campaignInfo = "Campaign: " . ($raw['campaign_name'] ?? 'N/A') . " | Ad: " . ($raw['ad_name'] ?? 'N/A');
            $notes = trim($campaignInfo . "\n" . $notes);
        }
        $normalized['notes'] = $notes ?: null;

        // Optional assigned salesperson
        if (!empty($raw['assigned_salesperson_id'])) {
            $normalized['assigned_salesperson_id'] = $raw['assigned_salesperson_id'];
        }

        return $normalized;
    }

    /**
     * Map flexible source string to standard lead sources.
     */
    protected function normalizeSourceName(string $rawSource): string
    {
        $s = strtolower(trim($rawSource));

        if (str_contains($s, 'facebook') || $s === 'fb') {
            return 'Facebook';
        }
        if (str_contains($s, 'instagram') || $s === 'ig' || $s === 'insta') {
            return 'Instagram';
        }
        if (str_contains($s, 'linkedin') || $s === 'li') {
            return 'LinkedIn';
        }
        if (str_contains($s, 'whatsapp') || $s === 'wa') {
            return 'WhatsApp';
        }
        if (str_contains($s, 'referral') || str_contains($s, 'ref')) {
            return 'Referral';
        }
        if (str_contains($s, 'website') || str_contains($s, 'web') || str_contains($s, 'google') || str_contains($s, 'organic')) {
            return 'Website';
        }
        if (str_contains($s, 'email') || str_contains($s, 'newsletter')) {
            return 'Email';
        }
        if (str_contains($s, 'phone') || str_contains($s, 'call')) {
            return 'Phone';
        }
        if (str_contains($s, 'exhibition') || str_contains($s, 'event')) {
            return 'Exhibition';
        }

        return ucfirst($rawSource);
    }
}

