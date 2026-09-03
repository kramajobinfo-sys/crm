<?php
namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly SalesService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => 'nullable|string|max:191',
            'method' => 'nullable|string|in:cash,card,bank_transfer,cheque,online',
            'customer_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->paginated($this->sales->paginatePayments($f, (int) ($f['per_page'] ?? 25)), PaymentResource::class);
    }

    public function destroy(int $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $this->sales->deletePayment($payment);
        return $this->success(null, 'Payment deleted');
    }
}
