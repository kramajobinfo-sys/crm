<?php
namespace App\Services;

use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Documents library (Zoho gap #9). Files are stored on the PRIVATE `local` disk under a
 * company-scoped, hashed path; the DB row's company_id (via BelongsToCompany) is the auth gate,
 * never the path. See docs/DOCUMENTS_SCOPE.md.
 */
class DocumentService
{
    private const DISK = 'local';

    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Document::query()
            ->with(['folder:id,name', 'uploader:id,name'])
            ->search($f['q'] ?? null)
            ->when(array_key_exists('folder_id', $f) && $f['folder_id'] !== '' && $f['folder_id'] !== null,
                fn ($q) => $q->where('folder_id', $f['folder_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): Document
    {
        return Document::with(['folder:id,name', 'uploader:id,name'])->findOrFail($id);
    }

    /** Store an uploaded file as a new document. */
    public function create(UploadedFile $file, array $data): Document
    {
        $companyId = auth()->user()->company_id;
        // store() generates a hashed filename — never the client's name (path-traversal safe).
        $path = $file->store('documents/'.$companyId.'/'.date('Y/m'), self::DISK);

        return $this->find(Document::create([
            'company_id'  => $companyId,
            'folder_id'   => $data['folder_id'] ?? null,
            'name'        => $data['name'] ?? $file->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'disk'        => self::DISK,
            'path'        => $path,
            'mime'        => $file->getMimeType(),      // server-guessed, not the spoofable client type
            'size'        => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ])->id);
    }

    /** Update metadata only (name / folder / description). */
    public function update(Document $document, array $data): Document
    {
        $document->update(array_intersect_key($data, array_flip(['name', 'folder_id', 'description'])));
        return $this->find($document->id);
    }

    /**
     * Replace the file. The new file is stored and the row repointed inside a transaction; the old
     * file is deleted only AFTER commit (best-effort) so the row is never left pointing at nothing.
     */
    public function replaceFile(Document $document, UploadedFile $file): Document
    {
        $companyId = $document->company_id;
        $oldDisk = $document->disk;
        $oldPath = $document->path;

        $newPath = $file->store('documents/'.$companyId.'/'.date('Y/m'), self::DISK);

        DB::transaction(function () use ($document, $file, $newPath) {
            $document->update([
                'disk' => self::DISK,
                'path' => $newPath,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        });

        if ($oldPath && $oldPath !== $newPath) {
            try { Storage::disk($oldDisk ?: self::DISK)->delete($oldPath); } catch (\Throwable) { /* orphan, harmless */ }
        }
        return $this->find($document->id);
    }

    /** Remove the row, then best-effort delete the file (row first — a failed unlink is a harmless orphan). */
    public function delete(Document $document): void
    {
        $disk = $document->disk ?: self::DISK;
        $path = $document->path;
        $document->delete();
        if ($path) {
            try { Storage::disk($disk)->delete($path); } catch (\Throwable) { /* orphan, harmless */ }
        }
    }

    // ---- folders ---------------------------------------------------------

    public function folders(): \Illuminate\Support\Collection
    {
        return DocumentFolder::withCount('documents')->orderBy('name')->get(['id', 'name']);
    }

    public function createFolder(array $data): DocumentFolder
    {
        return DocumentFolder::create([
            'company_id' => auth()->user()->company_id,
            'name' => $data['name'],
            'created_by' => auth()->id(),
        ]);
    }

    public function updateFolder(DocumentFolder $folder, array $data): DocumentFolder
    {
        $folder->update(['name' => $data['name']]);
        return $folder;
    }

    /** Delete a folder; its documents fall back to unfiled (FK nullOnDelete). Files are untouched. */
    public function deleteFolder(DocumentFolder $folder): void
    {
        $folder->delete();
    }
}
