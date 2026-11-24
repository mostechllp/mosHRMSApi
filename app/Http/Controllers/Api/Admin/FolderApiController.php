<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FolderApiController extends ApiController
{
    /**
     * Flat list with parent_id + full_path so the frontend can build a tree
     * or breadcrumb picker itself. Pass ?parent_id= to list one level only
     * (parent_id=null / omitted for top-level "main" folders).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Folder::orderBy('name');

        if ($request->has('parent_id') && $request->input('parent_id') !== '') {
            // parent_id given -> fetch that folder's direct subfolders
            $query->where('parent_id', $request->input('parent_id'));
        } else {
            // no parent_id -> fetch only main/top-level folders
            $query->whereNull('parent_id');
        }

        $folders = $query->get()->map(fn(Folder $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'parent_id' => $f->parent_id,
            'full_path' => $f->full_path,
            'has_children' => $f->children()->exists(),
        ]);

        return $this->success($folders);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:folders,name,NULL,id,parent_id,' . ($request->input('parent_id') ?: 'NULL') . ',deleted_at,NULL',
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $parent = $request->filled('parent_id') ? Folder::findOrFail($request->parent_id) : null;

        $folder = Folder::create([
            'name' => $request->name,
            'parent_id' => $parent?->id,
            'created_by' => Auth::id(),
        ]);

        // Physical directory mirrors the logical path, e.g. documents/HR/Contracts
        $dir = 'documents/' . $folder->full_path;
        if (!Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->makeDirectory($dir);
        }

        return $this->success($folder, 'Folder created successfully', 201);
    }

    public function show(Folder $folder): JsonResponse
    {
        return $this->success([
            'id' => $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id,
            'full_path' => $folder->full_path,
            'children' => $folder->children()->orderBy('name')->get(['id', 'name', 'parent_id']),
        ]);
    }

    /**
     * Rename and/or move (re-parent) a folder. Physically moving the directory
     * carries every nested subfolder and file with it in one operation, but
     * documents' stored file_path column needs updating to match afterward.
     */
    public function update(Request $request, Folder $folder): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:folders,name,' . $folder->id . ',id,parent_id,' . ($request->input('parent_id', $folder->parent_id) ?: 'NULL') . ',deleted_at,NULL',
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        if ($request->filled('parent_id') && (int) $request->parent_id === $folder->id) {
            return $this->error('A folder cannot be its own parent.', 422);
        }

        $oldPath = 'documents/' . $folder->full_path;
        $descendantIds = $folder->descendantIds();

        if (in_array((int) $request->input('parent_id'), $descendantIds, true)) {
            return $this->error('Cannot move a folder into its own subfolder.', 422);
        }

        $folder->name = $request->name;
        $folder->parent_id = $request->has('parent_id') ? ($request->parent_id ?: null) : $folder->parent_id;
        $folder->save();
        $folder->refresh();

        $newPath = 'documents/' . $folder->full_path;

        if ($oldPath !== $newPath) {
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->move($oldPath, $newPath);
            } else {
                Storage::disk('public')->makeDirectory($newPath);
            }

            // Directory move already relocated every nested file physically;
            // now sync the DB file_path column for this folder + all descendants.
            $affectedFolderIds = array_merge([$folder->id], $descendantIds);

            Document::whereIn('folder_id', $affectedFolderIds)
                ->get()
                ->each(function (Document $doc) use ($oldPath, $newPath) {
                    if ($doc->file_path && str_starts_with($doc->file_path, $oldPath . '/')) {
                        $doc->file_path = $newPath . substr($doc->file_path, strlen($oldPath));
                        $doc->saveQuietly();
                    }
                });
        }

        return $this->success($folder, 'Folder updated successfully');
    }

    /**
     * Soft-delete the folder, all its subfolders, and the documents in all of them.
     * Physical files are left in place (they're recoverable while soft-deleted).
     */
    public function destroy(Folder $folder): JsonResponse
    {
        $folderIds = array_merge([$folder->id], $folder->descendantIds());

        Document::whereIn('folder_id', $folderIds)->update(['deleted_by' => Auth::id()]);
        Document::whereIn('folder_id', $folderIds)->delete();

        Folder::whereIn('id', $folderIds)
            ->update(['deleted_by' => Auth::id()]);
        Folder::whereIn('id', $folderIds)->delete();

        return $this->success(null, 'Folder deleted successfully');
    }
}