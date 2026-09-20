<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\Warning;
use App\Mail\WarningNoticeMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Warnings',
    description: 'Endpoints for managing employee warning messages and sending warning emails'
)]
class WarningApiController extends ApiController
{
    /**
     * Display a listing of warnings.
     */
    #[OA\Get(
        path: '/api/admin/warnings',
        operationId: 'listWarnings',
        summary: 'List all employee warning messages',
        description: 'Returns a paginated list of employee warnings with optional employee_id filter and search query.',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\Parameter(name: 'employee_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1))]
    #[OA\Response(response: 200, description: 'Warnings fetched successfully')]
    public function index(Request $request): JsonResponse
    {
        $query = Warning::with([
            'employee.user.department',
            'employee.user.designation',
            'creator',
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                    });
            });
        }

        $warnings = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->success($warnings, 'Warnings fetched successfully.');
    }

    /**
     * Store a newly created warning in storage.
     */
    #[OA\Post(
        path: '/api/admin/warnings',
        operationId: 'createWarning',
        summary: 'Create a new employee warning message',
        description: 'Creates a warning for an employee, optionally with a letter attachment. If send_email is true, immediately dispatches the warning email.',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['employee_id', 'title', 'subject', 'description'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string', example: 'First Written Warning - Attendance'),
                    new OA\Property(property: 'subject', type: 'string', example: 'Notice of Unexcused Absences'),
                    new OA\Property(property: 'description', type: 'string', example: 'You have been absent for 3 consecutive days without prior notice.'),
                    new OA\Property(property: 'issued_date', type: 'string', format: 'date', example: '2026-09-16'),
                    new OA\Property(property: 'send_email', type: 'boolean', example: false),
                    new OA\Property(
                        property: 'attachment',
                        type: 'string',
                        format: 'binary',
                        description: 'Warning letter file (pdf, doc, docx, jpg, png — max 5MB)'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Warning created successfully')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'issued_date' => 'nullable|date',
            'send_email' => 'nullable|boolean',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120', // 5MB
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentPath = $file->store('warnings/attachments', 'public'); // switch disk to 's3' if needed
        }

        $warning = Warning::create([
            'employee_id' => $employee->id,
            'title' => $validated['title'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'issued_date' => $validated['issued_date'] ?? now()->toDateString(),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'created_by' => $request->user()?->id,
            'status' => 'issued',
        ]);

        if (!empty($validated['send_email'])) {
            $this->dispatchWarningEmail($warning);
        }

        return $this->success(
            $warning->load(['employee.user', 'creator']),
            'Warning message created successfully.',
            201
        );
    }

    /**
     * Display the specified warning.
     */
    #[OA\Get(
        path: '/api/admin/warnings/{id}',
        operationId: 'showWarning',
        summary: 'Get details of a warning message',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\Response(response: 200, description: 'Warning details fetched successfully')]
    #[OA\Response(response: 404, description: 'Warning record not found')]
    public function show($id): JsonResponse
    {
        $warning = Warning::with(['employee.user.department', 'employee.user.designation', 'creator'])->find($id);

        if (!$warning) {
            return $this->error('Warning record not found.', 404);
        }

        return $this->success($warning, 'Warning details fetched successfully.');
    }

    /**
     * Update the specified warning in storage.
     */
    #[OA\Put(
        path: '/api/admin/warnings/{id}',
        operationId: 'updateWarning',
        summary: 'Update an existing warning message',
        description: 'Updates warning fields. Optionally replace the attachment (send remove_attachment=true to clear it without uploading a new one).',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\RequestBody(
        required: false,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'subject', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'issued_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(
                        property: 'attachment',
                        type: 'string',
                        format: 'binary',
                        description: 'New warning letter file — replaces the existing one if present'
                    ),
                    new OA\Property(property: 'remove_attachment', type: 'boolean', example: false),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Warning updated successfully')]
    #[OA\Response(response: 404, description: 'Warning record not found')]
    public function update(Request $request, $id): JsonResponse
    {
        $warning = Warning::find($id);

        if (!$warning) {
            return $this->error('Warning record not found.', 404);
        }

        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'title' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'issued_date' => 'nullable|date',
            'status' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'remove_attachment' => 'nullable|boolean',
        ]);

        $updateData = array_filter(
            collect($validated)->except(['attachment', 'remove_attachment'])->all(),
            fn ($val) => !is_null($val)
        );

        // Replace attachment with a newly uploaded file
        if ($request->hasFile('attachment')) {
            if ($warning->attachment_path) {
                Storage::disk('public')->delete($warning->attachment_path);
            }

            $file = $request->file('attachment');
            $updateData['attachment_name'] = $file->getClientOriginalName();
            $updateData['attachment_path'] = $file->store('warnings/attachments', 'public');
        }
        // Or explicitly clear the existing attachment without uploading a new one
        elseif ($request->boolean('remove_attachment') && $warning->attachment_path) {
            Storage::disk('public')->delete($warning->attachment_path);
            $updateData['attachment_path'] = null;
            $updateData['attachment_name'] = null;
        }

        $warning->update($updateData);

        return $this->success(
            $warning->fresh(['employee.user', 'creator']),
            'Warning message updated successfully.'
        );
    }

    /**
     * Remove the specified warning from storage.
     */
    #[OA\Delete(
        path: '/api/admin/warnings/{id}',
        operationId: 'deleteWarning',
        summary: 'Delete a warning message',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\Response(response: 200, description: 'Warning deleted successfully')]
    public function destroy($id): JsonResponse
    {
        $warning = Warning::find($id);

        if (!$warning) {
            return $this->error('Warning record not found.', 404);
        }

        if ($warning->attachment_path) {
            Storage::disk('public')->delete($warning->attachment_path);
        }

        $warning->delete();

        return $this->success(null, 'Warning message deleted successfully.');
    }

    /**
     * Download the warning's attachment.
     */
    #[OA\Get(
        path: '/api/admin/warnings/{id}/attachment',
        operationId: 'downloadWarningAttachment',
        summary: 'Download the warning letter attachment',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\Response(response: 200, description: 'Attachment file stream')]
    #[OA\Response(response: 404, description: 'Warning or attachment not found')]
    public function downloadAttachment($id)
    {
        $warning = Warning::find($id);

        if (!$warning) {
            return $this->error('Warning record not found.', 404);
        }

        if (!$warning->attachment_path || !Storage::disk('public')->exists($warning->attachment_path)) {
            return $this->error('No attachment found for this warning.', 404);
        }

        return Storage::disk('public')->download(
            $warning->attachment_path,
            $warning->attachment_name ?? basename($warning->attachment_path)
        );
    }

    /**
     * Send warning email to the employee.
     */
    #[OA\Post(
        path: '/api/admin/warnings/{id}/send-email',
        operationId: 'sendWarningEmail',
        summary: 'Send warning email to employee',
        description: 'Sends the warning notification email to the employee\'s registered email address.',
        security: [['bearerAuth' => []]],
        tags: ['Warnings']
    )]
    #[OA\Response(response: 200, description: 'Warning email sent successfully')]
    public function sendEmail(Request $request, $id): JsonResponse
    {
        $warning = Warning::with(['employee.user'])->find($id);

        if (!$warning) {
            return $this->error('Warning record not found.', 404);
        }

        $email = $warning->employee?->personal_email ?? $warning->employee?->user?->email;

        if (!$email) {
            return $this->error('No valid email address found for this employee.', 422);
        }

        try {
            Mail::to($email)->send(new WarningNoticeMail($warning));

            $warning->update([
                'status' => 'sent',
                'email_sent_at' => now(),
            ]);

            return $this->success(
                $warning->fresh(),
                'Warning email sent successfully to ' . $email . '.'
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send warning email: ' . $e->getMessage());

            return $this->error('Failed to send warning email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper to dispatch warning email.
     */
    private function dispatchWarningEmail(Warning $warning): void
    {
        $warning->loadMissing(['employee.user']);
        $email = $warning->employee?->personal_email ?? $warning->employee?->user?->email;

        if ($email) {
            try {
                Mail::to($email)->send(new WarningNoticeMail($warning));

                $warning->update([
                    'status' => 'sent',
                    'email_sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to dispatch warning email for warning ID ' . $warning->id . ': ' . $e->getMessage());
            }
        }
    }
}