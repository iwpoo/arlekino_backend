<?php

namespace App\Http\Controllers\API\v1\Shop;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReturnRequest;
use App\Http\Requests\RejectReturnRequest;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\Contracts\ReturnsProcessingServiceInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ReturnsProcessingServiceInterface $returnsProcessingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorize('viewAny', OrderReturn::class);

        $preferredCurrency = $this->returnsProcessingService->getUserPreferredCurrency($user);

        $query = OrderReturn::with(['order', 'items.product.files', 'user', 'seller']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($user->isClient()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isSeller()) {
            $query->where('seller_id', $user->id);
        }

        $returns = $query->orderBy('created_at', 'desc')->paginate(15);

        $this->returnsProcessingService->convertReturnPrices($returns, $preferredCurrency);

        return response()->json($returns);
    }

    public function show(OrderReturn $return): JsonResponse
    {
        $this->authorize('view', $return);

        $user = Auth::user();
        $preferredCurrency = $this->returnsProcessingService->getUserPreferredCurrency($user);

        $return->load(['order', 'items.product.files', 'user', 'seller']);

        $this->returnsProcessingService->convertReturnPrices(collect([$return]), $preferredCurrency);

        return response()->json($return);
    }

    public function store(CreateReturnRequest $request): JsonResponse
    {
        $this->authorize('create', OrderReturn::class);

        $result = $this->returnsProcessingService->processReturnCreation($request);

        if ($result['success']) {
            return response()->json($result['return'], 201);
        } else {
            if (isset($result['errors'])) {
                return response()->json(['errors' => $result['errors']], 422);
            } else {
                return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
            }
        }
    }

    public function approve(OrderReturn $return, Request $request): JsonResponse
    {
        $this->authorize('approve', $return);

        $result = $this->returnsProcessingService->processReturnApproval($return, $request);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function reject(OrderReturn $return, RejectReturnRequest $request): JsonResponse
    {
        $this->authorize('reject', $return);

        $result = $this->returnsProcessingService->processReturnRejection($return, $request);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            if (isset($result['errors'])) {
                return response()->json(['errors' => $result['errors']], 422);
            } else {
                return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
            }
        }
    }

    public function markInTransit(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::IN_TRANSIT->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::IN_TRANSIT->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markReceived(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::RECEIVED->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::RECEIVED->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markConditionOk(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::CONDITION_OK->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::CONDITION_OK->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markConditionBad(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::CONDITION_BAD->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::CONDITION_BAD->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            if (isset($result['errors'])) {
                return response()->json(['errors' => $result['errors']], 422);
            } else {
                return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
            }
        }
    }

    public function markRejectedByWarehouse(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::REJECTED_BY_WAREHOUSE->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::REJECTED_BY_WAREHOUSE->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markRefundInitiated(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::REFUND_INITIATED->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::REFUND_INITIATED->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markCompleted(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::COMPLETED->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::COMPLETED->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function getEligibleItems(Order $order): JsonResponse
    {
        $user = Auth::user();
        if (($user->isClient() && $order->user_id !== $user->id) ||
            ($user->isSeller() && !$order->sellerOrders->contains('seller_id', $user->id))) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $result = $this->returnsProcessingService->getEligibleItems($order);

        return response()->json($result);
    }

    public function generateQR(OrderReturn $return): JsonResponse
    {
        $this->authorize('generateQR', $return);

        $result = $this->returnsProcessingService->generateReturnQR($return);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function scanQR(Request $request, string $qrCode = null): JsonResponse
    {
        $qrCode = $qrCode ?? $request->input('qr_code');

        $return = OrderReturn::where('qr_code', $qrCode)
            ->orWhere('second_qr_code', $qrCode)
            ->first();

        if ($return) {
            $this->authorize('scanQR', $return);
        }

        $result = $this->returnsProcessingService->scanReturnQR($qrCode, $request);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function regenerateQR(OrderReturn $return): JsonResponse
    {
        $this->authorize('regenerateQR', $return);

        $result = $this->returnsProcessingService->regenerateReturnQR($return);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }

    public function markInTransitBackToCustomer(OrderReturn $return): JsonResponse
    {
        $this->authorize('updateStatus', [OrderReturn::class, $return, ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value]);

        $result = $this->returnsProcessingService->processReturnStatusUpdate($return, ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value);

        if ($result['success']) {
            return response()->json($result['return']);
        } else {
            return response()->json(['error' => $result['error']], $result['error'] === 'Unauthorized' ? 403 : 400);
        }
    }
}
