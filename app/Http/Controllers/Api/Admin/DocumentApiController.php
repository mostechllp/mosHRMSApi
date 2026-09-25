<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class DocumentApiController extends ApiController
{
    /**
     * Directory documents with no folder_id are stored under (the "main" folder).
     */
    private const ROOT_DIR = 'documents/general';

    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type');
        $folderId = $request->get('folder_id');
        $perPage = $request->get('per_page', 15);

        // leftJoin, not join — folder_id is nullable now, an inner join would
        // silently drop every document that lives in the main folder.
        $query = Document::leftJoin('folders', 'documents.folder_id', '=', 'folders.id')
            ->select('documents.*', 'folders.name as folder_name')
            ->with(['party', 'folder'])
            ->latest('documents.created_at');

        if ($type) {
            $query->where('documents.type', $type);
        }

        if ($request->has('folder_id')) {
            // Explicit "main folder only" filter: ?folder_id= (empty) or ?folder_id=null
            if ($folderId === '' || $folderId === 'null') {
                $query->whereNull('documents.folder_id');
            } else {
                $query->where('documents.folder_id', $folderId);
            }
        }

        $documents = $query->get();
        $documents->each->append('shared_users');

        return $this->success($documents);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('temp', 'public');

            return $this->success([
                'path' => $path,
                'filename' => $file->getClientOriginalName()
            ], 'File uploaded to temporary storage');
        }

        return $this->error('No file uploaded', 400);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:organization,agreements,hr,others',
            'description' => 'nullable|string',
            'file_path' => 'required|string|starts_with:temp/',
            'folder_id' => 'nullable|exists:folders,id', // null/omitted -> main folder
            'party_id' => 'nullable|exists:parties,id',
            'share_with' => 'nullable|array',
            'expiry_date' => 'nullable|date|after:today'
        ]);

        $tempPath = $request->file_path;

        // Ensure temporary file exists
        if (!Storage::disk('public')->exists($tempPath)) {
            return $this->error('Temporary file not found', 404);
        }

        $destinationDir = $this->resolveFolderDirectory($request->folder_id);
        $filename = $this->uniqueFilename($destinationDir, basename($tempPath));
        $newPath = $destinationDir . '/' . $filename;

        // Move file from temp to final destination
        Storage::disk('public')->move($tempPath, $newPath);

        $document = Document::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'file_path' => $newPath,
            'folder_id' => $request->folder_id, // null = main folder
            'party_id' => $request->party_id,
            'share_with' => $request->share_with ?? [],
            'expiry_date' => $request->expiry_date
        ]);

        return $this->success($document->load(['party', 'folder']), 'Document created successfully', 201);
    }

    public function show(Document $document): JsonResponse
    {
        return $this->success($document->load(['party', 'folder'])->append('shared_users'));
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:organization,agreements,hr,others',
            'description' => 'nullable|string',
            'file_path' => 'sometimes|nullable|string|starts_with:temp/',
            'folder_id' => 'nullable|exists:folders,id',
            'party_id' => 'nullable|exists:parties,id',
            'share_with' => 'nullable|array',
            'expiry_date' => 'nullable|date|after:today'
        ]);

        $data = $request->only([
            'name',
            'type',
            'description',
            'party_id',
            'share_with',
            'expiry_date'
        ]);

        // folder_id can legitimately be set to null (moving a doc back to the
        // main folder), so read it explicitly instead of via array_filter.
        $newFolderId = $request->has('folder_id') ? $request->folder_id : $document->folder_id;
        $folderChanged = (int) $newFolderId !== (int) $document->folder_id
            || ($newFolderId === null) !== ($document->folder_id === null);

        $data['folder_id'] = $newFolderId;

        // Case 1: a new file was uploaded — replace old file, store in the (possibly new) folder
        if ($request->filled('file_path')) {
            $tempPath = $request->file_path;

            if (!Storage::disk('public')->exists($tempPath)) {
                return $this->error('Temporary file not found', 404);
            }

            if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $destinationDir = $this->resolveFolderDirectory($newFolderId);
            $filename = $this->uniqueFilename($destinationDir, basename($tempPath));
            $newPath = $destinationDir . '/' . $filename;

            Storage::disk('public')->move($tempPath, $newPath);
            $data['file_path'] = $newPath;
        }
        // Case 2: no new file, but folder changed — move the existing file along with it
        elseif ($folderChanged && $document->file_path && Storage::disk('public')->exists($document->file_path)) {
            $destinationDir = $this->resolveFolderDirectory($newFolderId);
            $filename = $this->uniqueFilename($destinationDir, basename($document->file_path));
            $newPath = $destinationDir . '/' . $filename;

            Storage::disk('public')->move($document->file_path, $newPath);
            $data['file_path'] = $newPath;
        }

        $document->update($data);

        return $this->success(
            $document->fresh()->load(['party', 'folder']),
            'Document updated successfully'
        );
    }

    public function destroy(Document $document): JsonResponse
    {
        // Delete file from storage
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
        return $this->success(null, 'Document deleted successfully');
    }

    /**
     * Folder listing lives on FolderApiController now (it returns full_path,
     * parent_id, nesting, etc.). Kept here only so old frontend calls to
     * this endpoint don't 404 — delegates straight through.
     *
     * @deprecated use FolderApiController@index instead
     */
    public function getFolders(): JsonResponse
    {
        $folders = Folder::orderBy('name')->get()->map(fn(Folder $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'parent_id' => $f->parent_id,
            'full_path' => $f->full_path,
        ]);

        return $this->success($folders);
    }

    public function getShareableUsers(): JsonResponse
    {
        $users = \App\Models\User::whereIn('type', ['admin', 'manager'])
            ->with('employee')
            ->get(['id', 'username', 'type']);

        $formatted = $users->map(function ($user) {
            $name = $user->employee
                ? $user->employee->first_name . ' ' . $user->employee->last_name
                : $user->username;

            return [
                'id' => $user->id,
                'name' => trim($name),
                'type' => $user->type
            ];
        });

        return $this->success($formatted);
    }

    /**
     * Resolve the storage directory for a document based on folder_id.
     * null/absent -> main/root documents directory.
     * Set -> the folder's full nested path, e.g. "documents/HR/Contracts".
     */
    private function resolveFolderDirectory(?int $folderId): string
    {
        if (!$folderId) {
            return self::ROOT_DIR;
        }

        $folder = Folder::find($folderId);

        if (!$folder) {
            return self::ROOT_DIR;
        }

        return 'documents/' . $folder->full_path;
    }

    /**
     * Avoid overwriting an existing file with the same name in the
     * destination folder by appending a short random suffix on collision.
     */
    private function uniqueFilename(string $directory, string $filename): string
    {
        if (!Storage::disk('public')->exists($directory . '/' . $filename)) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return $basename . '-' . Str::random(6) . ($extension ? '.' . $extension : '');
    }
}