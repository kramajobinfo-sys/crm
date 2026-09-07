<?php
namespace App\Http\Controllers\Api\V1\Imports;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk CSV import for leads / contacts / customers. Authorization is per-entity (the target's
 * *.create permission); a batch is company-scoped by BelongsToCompany.
 */
class ImportController extends Controller
{
    private const PERMS = ['lead' => 'leads.create', 'contact' => 'contacts.create', 'customer' => 'customers.create'];

    public function __construct(private ImportService $imports) {}

    public function meta(Request $request): JsonResponse
    {
        $entities = collect($this->imports->entities())
            ->filter(fn ($e) => $request->user()->can(self::PERMS[$e['key']]))->values();
        return $this->success(['entities' => $entities]);
    }

    /** Upload a CSV, get back detected columns + a suggested mapping. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity' => ['required', Rule::in(array_keys(self::PERMS))],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv'],
        ]);
        $this->authorizeEntity($request, $data['entity']);
        $payload = $this->imports->createBatch($data['entity'], $request->file('file'),
            $request->user()->company_id, $request->user()->id);
        return $this->success($payload, 'File uploaded', 201);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $this->authorizeEntity($request, $batch->entity);
        $data = $request->validate(['mapping' => 'required|array']);
        return $this->success($this->imports->preview($batch, $data['mapping']));
    }

    public function commit(Request $request, int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $this->authorizeEntity($request, $batch->entity);
        $data = $request->validate([
            'mapping' => 'required|array',
            'dedupe' => ['nullable', Rule::in(['skip', 'update'])],
        ]);
        $batch = $this->imports->commit($batch, $data['mapping'], $data['dedupe'] ?? 'skip');
        return $this->success($this->imports->present($batch), 'Import started', 202);
    }

    /** Batch status + a page of error rows (for the error report). Polled by the UI while processing. */
    public function show(Request $request, int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $this->authorizeEntity($request, $batch->entity);
        $errors = $batch->rows()->orderBy('row_number')->paginate(50);
        return $this->success([
            'batch' => $this->imports->present($batch),
            'errors' => $errors->items(),
            'errors_meta' => ['total' => $errors->total(), 'current_page' => $errors->currentPage(), 'last_page' => $errors->lastPage()],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $batches = ImportBatch::with('user:id,name')->latest('id')->paginate((int) $request->query('per_page', 25));
        return $this->paginated($batches->through(fn ($b) => array_merge($this->imports->present($b),
            ['user' => $b->user?->name])));
    }

    /** Downloadable CSV of the rows that failed, with their error messages. */
    public function errorsCsv(Request $request, int $id): StreamedResponse
    {
        $batch = ImportBatch::findOrFail($id);
        $this->authorizeEntity($request, $batch->entity);
        $filename = 'import-'.$batch->id.'-errors.csv';
        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row', 'errors', 'data']);
            $batch->rows()->orderBy('row_number')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [$r->row_number, implode('; ', $r->errors ?? []), json_encode($r->data)]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function authorizeEntity(Request $request, string $entity): void
    {
        abort_unless($request->user()->can(self::PERMS[$entity] ?? 'nope'), 403,
            'You do not have permission to import '.$entity.'s.');
    }
}
