<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreLeadFollowUpRequest;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadFollowUpApiController extends ApiController
{
    /**
     * Get all follow-ups for a specific lead.
     */
    public function index(Lead $lead): JsonResponse
    {
        $followUps = $lead->followUps()->with(['performer', 'creator'])->get();
        return $this->success($followUps);
    }

    /**
     * Store a new follow-up activity for a specific lead.
     */
    public function store(StoreLeadFollowUpRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();
        $validated['lead_id'] = $lead->id;
        $validated['created_by'] = auth()->id();
        if (empty($validated['performed_by'])) {
            $validated['performed_by'] = $lead->assigned_salesperson_id;
        }

        DB::beginTransaction();
        try {
            $followUp = LeadFollowUp::create($validated);

            // Update summary on lead table
            $lead->next_follow_up_at = $validated['follow_up_date'];
            $lead->follow_up_type    = $validated['follow_up_type'];
            $lead->follow_up_notes   = $validated['notes'] ?? $lead->follow_up_notes;
            $lead->updated_by        = auth()->id();
            $lead->save();

            DB::commit();

            return $this->success(
                $followUp->load(['performer', 'creator']),
                'Follow-up scheduled successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create follow-up: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an existing follow-up activity.
     */
    public function update(Request $request, LeadFollowUp $followUp): JsonResponse
    {
        $validated = $request->validate([
            'follow_up_type' => 'sometimes|required|string|max:50',
            'follow_up_date' => 'sometimes|required|date',
            'status'         => 'sometimes|required|string|in:scheduled,completed,cancelled,missed',
            'notes'          => 'nullable|string',
            'outcome'        => 'nullable|string',
            'performed_by'   => 'nullable|exists:employees,id',
        ]);

        $followUp->update($validated);

        return $this->success($followUp->load(['performer', 'creator']), 'Follow-up updated successfully');
    }

    /**
     * Delete a follow-up activity log.
     */
    public function destroy(LeadFollowUp $followUp): JsonResponse
    {
        $followUp->delete();
        return $this->success(null, 'Follow-up deleted successfully');
    }

    /**
     * Get pending / upcoming follow-ups across all leads.
     */
    public function pending(Request $request): JsonResponse
    {
        $query = LeadFollowUp::with(['lead.assignedSalesperson', 'performer'])
            ->where('status', 'scheduled');

        if ($request->filled('assigned_salesperson_id')) {
            $query->whereHas('lead', function ($q) use ($request) {
                $q->where('assigned_salesperson_id', $request->assigned_salesperson_id);
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('follow_up_date', $request->date);
        } else {
            // Default: show today & future pending followups
            $query->whereDate('follow_up_date', '>=', now()->toDateString());
        }

        $followUps = $query->orderBy('follow_up_date', 'asc')
            ->paginate($request->input('per_page', 15));

        return $this->success($followUps);
    }
}
