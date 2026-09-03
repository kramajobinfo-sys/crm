<?php
namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(private readonly PurchaseService $purchase) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'status' => 'nullable|string|in:all,active,on_hold,blocked',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->purchase->paginateVendors($f, (int) ($f['per_page'] ?? 25)), VendorResource::class);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new VendorResource(Vendor::findOrFail($id)));
    }

    public function store(StoreVendorRequest $request): JsonResponse
    {
        return $this->success(new VendorResource($this->purchase->createVendor($request->validated())), 'Vendor created', 201);
    }

    public function update(StoreVendorRequest $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        return $this->success(new VendorResource($this->purchase->updateVendor($vendor, $request->validated())), 'Vendor updated');
    }

    public function destroy(int $id): JsonResponse
    {
        Vendor::findOrFail($id)->delete();
        return $this->success(null, 'Vendor deleted');
    }
}
