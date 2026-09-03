<?php
namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): JsonResponse
    {
        return $this->success(WarehouseResource::collection($this->inventory->warehouses()));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        return $this->success(new WarehouseResource($this->inventory->createWarehouse($request->validated())), 'Warehouse created', 201);
    }

    public function update(StoreWarehouseRequest $request, int $id): JsonResponse
    {
        $w = Warehouse::findOrFail($id);
        return $this->success(new WarehouseResource($this->inventory->updateWarehouse($w, $request->validated())), 'Warehouse updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $w = Warehouse::withCount('stockItems')->findOrFail($id);
        if ($w->stock_items_count > 0) {
            return $this->error('This warehouse still holds stock and cannot be deleted.', 422);
        }
        $w->delete();
        return $this->success(null, 'Warehouse deleted');
    }
}
