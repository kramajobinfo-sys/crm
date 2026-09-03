<?php
namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const MAX_UPLOAD_KB = 15360;   // 15 MB, same ceiling as leads/deals/chat
    private const MIMES = [
        'image/jpeg','image/png','image/gif','image/webp',
        'application/pdf','text/plain','text/csv',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
    ];

    public function __construct(private readonly DocumentService $documents) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'folder_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->documents->paginate($f, (int) ($f['per_page'] ?? 25)), DocumentResource::class);
    }

    public function meta(): JsonResponse
    {
        return $this->success(['folders' => $this->documents->folders()]);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new DocumentResource($this->documents->find($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'file' => 'required|file|max:'.self::MAX_UPLOAD_KB.'|mimetypes:'.implode(',', self::MIMES),
            'name' => 'nullable|string|max:191',
            'description' => 'nullable|string|max:500',
            'folder_id' => ['nullable','integer', \Illuminate\Validation\Rule::exists('document_folders','id')->where('company_id',$companyId)],
        ]);
        $doc = $this->documents->create($request->file('file'), $data);
        return $this->success(new DocumentResource($doc), 'Document uploaded', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string|max:500',
            'folder_id' => ['nullable','integer', \Illuminate\Validation\Rule::exists('document_folders','id')->where('company_id',$companyId)],
        ]);
        $doc = Document::findOrFail($id);
        return $this->success(new DocumentResource($this->documents->update($doc, $data)), 'Document updated');
    }

    public function replaceFile(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:'.self::MAX_UPLOAD_KB.'|mimetypes:'.implode(',', self::MIMES),
        ]);
        $doc = Document::findOrFail($id);
        return $this->success(new DocumentResource($this->documents->replaceFile($doc, $request->file('file'))), 'File replaced');
    }

    /**
     * Stream the file. The row is resolved through the company-scoped model FIRST (findOrFail),
     * so a cross-tenant id is a 404 before any disk access; the path comes only from that row,
     * never from the request.
     */
    public function download(int $id): StreamedResponse
    {
        $doc = Document::findOrFail($id);
        $disk = Storage::disk($doc->disk ?: 'local');
        abort_unless($disk->exists($doc->path), 404);
        return $disk->download($doc->path, $doc->name);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->documents->delete(Document::findOrFail($id));
        return $this->success(null, 'Document deleted');
    }

    // ---- folders ---------------------------------------------------------

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:128']);
        $folder = $this->documents->createFolder($data);
        return $this->success(['id' => $folder->id, 'name' => $folder->name], 'Folder created', 201);
    }

    public function updateFolder(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:128']);
        $folder = DocumentFolder::findOrFail($id);
        $this->documents->updateFolder($folder, $data);
        return $this->success(['id' => $folder->id, 'name' => $folder->name], 'Folder updated');
    }

    public function destroyFolder(int $id): JsonResponse
    {
        $this->documents->deleteFolder(DocumentFolder::findOrFail($id));
        return $this->success(null, 'Folder deleted');
    }
}
