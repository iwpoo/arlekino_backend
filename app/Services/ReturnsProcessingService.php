<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Enums\ReturnMethod;
use App\Jobs\ProcessRefundPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\ReturnItem;
use App\Notifications\ReturnStatusNotification;
use App\Services\Contracts\ReturnsProcessingServiceInterface;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use DomainException;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Log;
use Throwable;

class ReturnsProcessingService implements ReturnsProcessingServiceInterface
{
    public function __construct(
        protected CurrencyConverter $currencyConverter
    ) {}

    public function processReturnCreation(Request $request): array
    {
        $user = Auth::user();
        $preferredCurrency = $this->getUserPreferredCurrency($user);

        $order = Order::find($request->order_id);
        if (!$order || $order->user_id !== $user->id) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        $eligibilityCheck = $this->checkReturnEligibility($order);
        if (!$eligibilityCheck['eligible']) {
            return ['success' => false, 'error' => $eligibilityCheck['error']];
        }

        return $this->createReturnTransaction($request, $order, $user, $preferredCurrency);
    }

    private function createReturnTransaction(Request $request, Order $order, $user, string $preferredCurrency): array
    {
        try {
            return DB::transaction(function () use ($request, $order, $user, $preferredCurrency) {
                try {
                    $return = new OrderReturn();
                    $return->order_id = $order->id;
                    $return->user_id = $user->id;
                    $return->seller_id = $order->sellerOrders->first()?->seller_id;
                    $return->status = ReturnStatus::PENDING->value;
                    $return->return_method = $request->return_method;
                    $return->save();

                    $processingResult = $this->processReturnItems($request->items, $return, $order);
                    if (!$processingResult['success']) {
                        throw new Exception($processingResult['error']);
                    }

                    $return->update([
                        'refund_amount' => $processingResult['totalRefundAmount'],
                        'logistics_cost' => $processingResult['logisticsCost']
                    ]);

                    $return->load(['order', 'items.product', 'user', 'seller']);
                    $this->convertReturnPrices(collect([$return]), $preferredCurrency);

                    $return->user->notify(new ReturnStatusNotification($return, 'pending'));
                    $return->seller?->notify(new ReturnStatusNotification($return, 'pending'));

                    return ['success' => true, 'return' => $return];
                } catch (Exception $e) {
                    Log::error("Return Creation Failed: " . $e->getMessage());
                    throw $e;
                }
            });
        } catch (DomainException $e) {
            Log::warning("Return validation failed: " . $e->getMessage(), ['order_id' => $order->id]);
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::critical("CRITICAL: Return Creation Failed", [
                'order_id' => $order->id,
                'user_id'  => $user->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при оформлении возврата. Попробуйте позже.'
            ];
        }
    }

    private function processReturnItems(array $items, OrderReturn $return, Order $order): array
    {
        $totalRefundAmount = 0;
        $logisticsCost = 0;

        foreach ($items as $itemData) {
            $orderItem = OrderItem::find($itemData['order_item_id']);
            if (!$orderItem || $orderItem->order_id !== $order->id) {
                return ['success' => false, 'error' => 'Invalid order item'];
            }

            $logisticsCost += $this->calculateLogisticsCost($itemData['reason']);
            $finalPrice = $orderItem->product->getFinalPrice();

            ReturnItem::create([
                'return_id' => $return->id,
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'quantity' => $orderItem->quantity,
                'price' => $finalPrice,
                'reason' => $itemData['reason'],
                'comment' => $itemData['comment'] ?? null,
                'photos' => $itemData['photos'] ?? null,
            ]);

            $totalRefundAmount += $finalPrice * $orderItem->quantity;
        }

        return [
            'success' => true,
            'totalRefundAmount' => $totalRefundAmount,
            'logisticsCost' => $logisticsCost
        ];
    }

    private function calculateLogisticsCost(string $reason): float
    {
        $freeReasons = explode(',', config('services.returns_processing.logistics_free_reasons', 'does_not_match_description,defective_damaged'));
        $logisticsCost = config('services.returns_processing.logistics_cost', 150.0);
        return in_array($reason, $freeReasons) ? 0 : $logisticsCost;
    }

    public function processReturnApproval(OrderReturn $return, Request $request): array
    {
        $return->update([
            'status' => ReturnStatus::APPROVED->value,
            'qr_code' => 'RETURN_' . strtoupper(Str::random(config('services.returns_processing.qr_code_length', 16))),
            'expires_at' => now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24))
        ]);

        $return->user->notify(new ReturnStatusNotification($return->load('user'), 'approved'));

        return ['success' => true, 'return' => $return];
    }

    public function processReturnRejection(OrderReturn $return, Request $request): array
    {
        $return->update([
            'status' => ReturnStatus::REJECTED->value,
            'rejection_reason' => $request->rejection_reason
        ]);

        $return->user->notify(new ReturnStatusNotification($return->load('user'), 'rejected'));

        return ['success' => true, 'return' => $return];
    }

    public function processReturnStatusUpdate(OrderReturn $return, string $newStatus): array
    {
        return match ($newStatus) {
            ReturnStatus::IN_TRANSIT->value => $this->markReturnInTransit($return),
            ReturnStatus::RECEIVED->value => $this->markReturnReceived($return),
            ReturnStatus::CONDITION_OK->value => $this->markReturnConditionOk($return),
            ReturnStatus::CONDITION_BAD->value => $this->markReturnConditionBad($return),
            ReturnStatus::REFUND_INITIATED->value => $this->markReturnRefundInitiated($return),
            ReturnStatus::COMPLETED->value => $this->markReturnCompleted($return),
            default => ['success' => false, 'error' => 'Invalid status'],
        };
    }

    private function markReturnInTransit(OrderReturn $return): array
    {
        if (!in_array($return->status, [ReturnStatus::APPROVED->value, ReturnStatus::CONDITION_BAD->value])) {
            return [
                'success' => false,
                'error' => 'Return is not in approved or condition bad status'
            ];
        }

        $newStatus = $return->status === ReturnStatus::CONDITION_BAD->value ? ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value : ReturnStatus::IN_TRANSIT->value;
        $return->status = $newStatus;
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        $return->user->notify(new ReturnStatusNotification($return, $newStatus));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    private function markReturnReceived(OrderReturn $return): array
    {
        if ($return->status !== ReturnStatus::IN_TRANSIT->value) {
            return [
                'success' => false,
                'error' => 'Return is not in transit status'
            ];
        }

        $return->status = ReturnStatus::RECEIVED->value;
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        $return->user->notify(new ReturnStatusNotification($return, 'received'));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    private function markReturnConditionOk(OrderReturn $return): array
    {
        if ($return->status !== ReturnStatus::RECEIVED->value) {
            return [
                'success' => false,
                'error' => 'Return is not in received status'
            ];
        }

        try {
            DB::beginTransaction();
            $return->status = ReturnStatus::CONDITION_OK->value;
            $return->save();

            $return->load(['order', 'items.product', 'user', 'seller']);

            $return->user->notify(new ReturnStatusNotification($return, 'condition_ok'));

            $return->status = ReturnStatus::REFUND_INITIATED->value;
            $return->save();

            ProcessRefundPayment::dispatch($return);

            DB::commit();

            $return->user->notify(new ReturnStatusNotification($return, 'refund_initiated', 'Возврат денег начат. Средства поступят в течение 3-7 дней'));

            return [
                'success' => true,
                'return' => $return
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Failed to process return condition: ' . $e->getMessage()
            ];
        }
    }

    private function markReturnConditionBad(OrderReturn $return): array
    {
        if (!in_array($return->status, [ReturnStatus::RECEIVED->value, ReturnStatus::APPROVED->value])) {
            return [
                'success' => false,
                'error' => 'Return is not in received or approved status'
            ];
        }

        try {
            DB::beginTransaction();
            $return->status = ReturnStatus::CONDITION_BAD->value;
            $return->rejection_reason = request()->rejection_reason ?? 'Item condition does not meet return requirements';
            $return->expires_at = now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24));
            $return->save();

            if (request()->hasFile('photos')) {
                $photos = [];
                foreach (request()->file('photos') as $photo) {
                    $path = $photo->store('return_photos', 'public');
                    $photos[] = Storage::url($path);
                }

                if ($return->items->count() > 0) {
                    $firstItem = $return->items->first();
                    $firstItem->photos = $photos;
                    $firstItem->save();
                }
            }

            DB::commit();

            $return->load(['order', 'items.product', 'user', 'seller']);

            $return->user->notify(new ReturnStatusNotification($return, 'condition_bad'));

            return [
                'success' => true,
                'return' => $return
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => 'Failed to process return condition: ' . $e->getMessage()
            ];
        }
    }

    private function markReturnRejectedByWarehouse(OrderReturn $return): array
    {
        if (!in_array($return->status, ['condition_bad', 'in_transit_back_to_customer'])) {
            return [
                'success' => false,
                'error' => 'Return is not in condition bad or in transit back to customer status'
            ];
        }

        $return->status = ReturnStatus::REJECTED_BY_WAREHOUSE->value;
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        $return->user->notify(new ReturnStatusNotification($return, 'rejected_by_warehouse'));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    /**
     * Mark return refund initiated
     */
    private function markReturnRefundInitiated(OrderReturn $return): array
    {
        if ($return->status !== ReturnStatus::CONDITION_OK->value) {
            return [
                'success' => false,
                'error' => 'Return is not in condition OK status'
            ];
        }

        $return->status = 'refund_initiated';
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        ProcessRefundPayment::dispatch($return);

        $return->user->notify(new ReturnStatusNotification($return, 'refund_initiated'));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    /**
     * Mark return completed
     */
    private function markReturnCompleted(OrderReturn $return): array
    {
        if ($return->status !== ReturnStatus::REFUND_INITIATED->value) {
            return [
                'success' => false,
                'error' => 'Return is not in refund initiated status'
            ];
        }

        $return->status = ReturnStatus::COMPLETED->value;
        $return->completed_at = now();
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        $return->user->notify(new ReturnStatusNotification($return, 'completed', 'Деньги возвращены на вашу карту'));
        $return->seller?->notify(new ReturnStatusNotification($return, 'completed', 'Возврат завершен'));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    /**
     * Mark return in transit back to customer
     */
    private function markReturnInTransitBackToCustomer(OrderReturn $return): array
    {
        if ($return->status !== ReturnStatus::CONDITION_BAD->value) {
            return [
                'success' => false,
                'error' => 'Return is not in condition bad status'
            ];
        }

        $return->status = ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value;
        $return->save();

        $return->load(['order', 'items.product', 'user', 'seller']);

        $return->user->notify(new ReturnStatusNotification($return, 'in_transit_back_to_customer'));

        return [
            'success' => true,
            'return' => $return
        ];
    }

    /**
     * Get user's preferred currency
     */
    public function getUserPreferredCurrency($user): string {
        return ($user && $user->currency) ? $user->currency : $this->currencyConverter->getBaseCurrency();
    }

    /**
     * Convert return prices to specified currency
     */
    public function convertReturnPrices(mixed $returns, string $toCurrency): void
    {
        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        if ($toCurrency === $baseCurrency) {
            return;
        }

        $items = [];
        if ($returns instanceof LengthAwarePaginator) {
            $items = $returns->items();
        } elseif (is_iterable($returns)) {
            $items = $returns;
        } elseif (is_object($returns)) {
            $items = [$returns];
        }

        foreach ($items as $return) {
            if (isset($return->refund_amount)) {
                $return->original_refund_amount = $return->refund_amount;
                $return->refund_amount = $this->currencyConverter->convert(
                    (float)$return->refund_amount,
                    $baseCurrency,
                    $toCurrency
                );
                $return->currency = $toCurrency;
            }

            if (isset($return->logistics_cost)) {
                $return->original_logistics_cost = $return->logistics_cost;
                $return->logistics_cost = $this->currencyConverter->convert(
                    (float)$return->logistics_cost,
                    $baseCurrency,
                    $toCurrency
                );
                $return->currency = $toCurrency;
            }

            if (isset($return->items)) {
                foreach ($return->items as $item) {
                    if (isset($item->price)) {
                        $item->original_price = $item->price;
                        $item->price = $this->currencyConverter->convert(
                            (float)$item->price,
                            $baseCurrency,
                            $toCurrency
                        );
                        $item->currency = $toCurrency;
                    }
                }
            }
        }
    }

    /**
     * Check if order is eligible for return
     */
    public function checkReturnEligibility(Order $order): array {
        if ($order->status !== ReturnStatus::COMPLETED->value) return ['eligible' => false, 'error' => 'Order not completed'];
        if ($order->updated_at->diffInDays(now()) > config('services.returns_processing.return_period_days', 14)) return ['eligible' => false, 'error' => 'Period expired'];
        return ['eligible' => true, 'error' => null];
    }

    /**
     * Get eligible items for return
     */
    public function getEligibleItems(Order $order): \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection
    {
        $user = Auth::user();

        if (!$user->isClient() || $order->user_id !== $user->id || $order->status !== ReturnStatus::COMPLETED->value ||
            $order->updated_at->diffInDays(now()) > config('services.returns_processing.return_period_days', 14)) {
            return collect();
        }

        $existingReturnItemIds = ReturnItem::whereIn('return_id', function ($query) {
            $query->select('id')
                ->from('order_returns')
                ->where('status', '!=', ReturnStatus::REJECTED->value)
                ->where('status', '!=', ReturnStatus::CONDITION_BAD->value);
        })
            ->pluck('order_item_id');

        return $order->items()
            ->with('product')
            ->whereNotIn('id', $existingReturnItemIds)
            ->get();
    }

    /**
     * Generate QR code for return
     */
    public function generateReturnQR(OrderReturn $return): array
    {
        if ($return->status === ReturnStatus::APPROVED->value) {
            if (!$return->qr_code || now()->gt($return->expires_at)) {
                $qrIdentifier = 'RETURN_' . strtoupper(Str::random(config('services.returns_processing.qr_code_length', 16)));

                $return->update([
                    'qr_code' => $qrIdentifier,
                    'expires_at' => now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24))
                ]);
            }

            $qrContent = $return->qr_code;

            return $this->generateQRCodeResponse($qrContent, $return, 'primary');
        } elseif ($return->status === ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value) {
            if (!$return->second_qr_code || now()->gt($return->second_expires_at)) {
                $qrIdentifier = 'RETURN_CUSTOMER_' . strtoupper(Str::random(config('services.returns_processing.qr_code_length', 16)));

                $return->update([
                    'second_qr_code' => $qrIdentifier,
                    'second_expires_at' => now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24))
                ]);
            }

            $qrContent = $return->second_qr_code;

            return $this->generateQRCodeResponse($qrContent, $return, 'secondary');
        } else {
            return [
                'success' => false,
                'error' => 'QR code can only be generated for approved or in_transit_back_to_customer returns'
            ];
        }
    }

    /**
     * Generate QR code response
     */
    private function generateQRCodeResponse(string $qrContent, OrderReturn $return, string $type): array
    {
        $cacheKey = "qr_code_img_" . md5($qrContent);
        $qrcode = cache()->remember($cacheKey, 3600, function() use ($qrContent) {
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_Q,
                'scale'      => 8,
            ]);
            return (new QRCode($options))->render($qrContent);
        });

        $expiresAtField = $type === 'primary' ? 'expires_at' : 'second_expires_at';
        $qrCodeField = $type === 'primary' ? 'qr_code' : 'second_qr_code';

        return [
            'success' => true,
            'qrcode' => $qrcode,
            'qr_code' => $return->$qrCodeField,
            'expires_at' => $return->$expiresAtField,
            'return_id' => $return->id,
        ];
    }

    /**
     * Regenerate QR code for return
     */
    public function regenerateReturnQR(OrderReturn $return): array
    {
        if ($return->status === ReturnStatus::APPROVED->value) {
            $qrIdentifier = 'RETURN_' . strtoupper(Str::random(config('services.returns_processing.qr_code_length', 16)));
            $expiresAt = now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24));

            $return->update([
                'qr_code' => $qrIdentifier,
                'expires_at' => $expiresAt,
            ]);

            $qrContent = $return->qr_code;

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_Q,
                'scale'      => 10,
            ]);

            $qrcode = (new QRCode($options))->render($qrContent);

            return [
                'success' => true,
                'qrcode' => $qrcode,
                'qr_code' => $return->qr_code,
                'expires_at' => $return->expires_at,
                'return_id' => $return->id,
                'message' => 'QR code regenerated successfully'
            ];
        } elseif ($return->status === ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value) {
            $qrIdentifier = 'RETURN_CUSTOMER_' . strtoupper(Str::random(config('services.returns_processing.qr_code_length', 16)));
            $expiresAt = now()->addHours(config('services.returns_processing.qr_code_expiry_hours', 24));

            $return->update([
                'second_qr_code' => $qrIdentifier,
                'second_expires_at' => $expiresAt,
            ]);

            $qrContent = $return->second_qr_code;

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_Q,
                'scale'      => 10,
            ]);

            $qrcode = (new QRCode($options))->render($qrContent);

            return [
                'success' => true,
                'qrcode' => $qrcode,
                'qr_code' => $return->second_qr_code,
                'expires_at' => $return->second_expires_at,
                'return_id' => $return->id,
                'message' => 'Second QR code regenerated successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'QR code can only be regenerated for approved or in_transit_back_to_customer returns'
            ];
        }
    }

    /**
     * Scan QR code and process return status
     */
    public function scanReturnQR(string $qrCode, Request $request): array
    {
        $user = Auth::user();

        if (!$qrCode) {
            return [
                'success' => false,
                'error' => 'QR code is required'
            ];
        }

        $return = OrderReturn::where('qr_code', $qrCode)
            ->orWhere('second_qr_code', $qrCode)
            ->first();

        if (!$return) {
            Log::warning('Invalid QR code scanned', [
                'qr_code' => $qrCode,
                'user_id' => $user->id ?? null,
                'ip' => $request->ip(),
                'timestamp' => now()
            ]);

            return [
                'success' => false,
                'error' => 'Invalid QR code'
            ];
        }

        if (!$return->id) {
            return [
                'success' => false,
                'error' => 'Invalid QR code'
            ];
        }

        $isSecondQR = ($return->second_qr_code === $qrCode);

        if ($isSecondQR) {
            if (now()->gt($return->second_expires_at)) {
                return [
                    'success' => false,
                    'error' => 'QR code has expired'
                ];
            }
        } else {
            if (now()->gt($return->expires_at)) {
                return [
                    'success' => false,
                    'error' => 'QR code has expired'
                ];
            }
        }

        if (in_array($return->status, [ReturnStatus::REFUND_INITIATED->value, ReturnStatus::COMPLETED->value, ReturnStatus::REJECTED->value, ReturnStatus::REJECTED_BY_WAREHOUSE->value])) {
            return [
                'success' => false,
                'error' => 'This return has already been processed'
            ];
        }

        if ($user->isClient() && $return->user_id !== $user->id) {
            Log::warning('Unauthorized QR code access attempt', [
                'return_id' => $return->id,
                'user_id' => $user->id,
                'actual_owner' => $return->user_id,
                'ip' => $request->ip(),
                'timestamp' => now()
            ]);

            return [
                'success' => false,
                'error' => 'This return is not associated with your account'
            ];
        }

        if ($user->isSeller() && $return->seller_id !== $user->id) {
            Log::warning('Unauthorized QR code access attempt', [
                'return_id' => $return->id,
                'user_id' => $user->id,
                'actual_seller' => $return->seller_id,
                'ip' => $request->ip(),
                'timestamp' => now()
            ]);

            return [
                'success' => false,
                'error' => 'This return is not associated with your store'
            ];
        }

        if (!in_array($return->return_method, [ReturnMethod::SELF_RETURN->value, ReturnMethod::COURIER_RETURN->value])) {
            return [
                'success' => false,
                'error' => 'Invalid return method'
            ];
        }

        if ($isSecondQR) {
            return $this->handleSecondQRScan($return, $user);
        } else {
            return $this->handlePrimaryQRScan($return, $user);
        }
    }

    /**
     * Handle second QR code scan
     */
    private function handleSecondQRScan(OrderReturn $return, $user): array
    {
        if ($return->status !== ReturnStatus::CONDITION_BAD->value) {
            return [
                'success' => false,
                'error' => 'This QR code can only be scanned when return status is condition_bad'
            ];
        }

        $return->update(['status' => ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value]);

        if ($user->isClient()) {
            $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_pickup_customer', 'Курьер забрал товар для возврата вам.'));
            Log::info('Return item picked up by courier for customer return', [
                'return_id' => $return->id,
                'user_id' => $user->id,
                'timestamp' => now()
            ]);
        } else {
            $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_pickup_customer', 'Продавец забрал товар для возврата вам.'));
            Log::info('Return item picked up by seller for customer return', [
                'return_id' => $return->id,
                'seller_id' => $user->id,
                'timestamp' => now()
            ]);
        }

        return [
            'success' => true,
            'message' => 'Item picked up for customer return. Waiting for delivery confirmation.',
            'status' => ReturnStatus::IN_TRANSIT_BACK_TO_CUSTOMER->value,
            'return' => $return->load(['order', 'items.product', 'user', 'seller'])
        ];
    }

    /**
     * Handle primary QR code scan
     */
    private function handlePrimaryQRScan(OrderReturn $return, $user): array
    {
        if ($return->return_method === ReturnMethod::COURIER_RETURN->value) {
            return $this->handleCourierReturnQRScan($return, $user);
        } elseif ($return->return_method === ReturnMethod::SELF_RETURN->value && $user->isSeller()) {
            return $this->handleSelfReturnQRScan($return, $user);
        } else {
            return [
                'success' => false,
                'error' => 'Unauthorized operation'
            ];
        }
    }

    /**
     * Handle courier return QR scan
     */
    private function handleCourierReturnQRScan(OrderReturn $return, $user): array
    {
        if ($return->status === ReturnStatus::APPROVED->value) {
            $return->update(['status' => ReturnStatus::IN_TRANSIT->value]);

            if ($user->isClient()) {
                $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_pickup', 'Курьер забрал товар для возврата.'));
                Log::info('Return item picked up by courier', [
                    'return_id' => $return->id,
                    'user_id' => $user->id,
                    'timestamp' => now()
                ]);
            } else {
                $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_pickup', 'Продавец забрал товар для возврата.'));
                Log::info('Return item picked up by seller', [
                    'return_id' => $return->id,
                    'seller_id' => $user->id,
                    'timestamp' => now()
                ]);
            }

            return [
                'success' => true,
                'message' => 'Item picked up. Waiting for delivery confirmation.',
                'status' => ReturnStatus::IN_TRANSIT->value,
                'return' => $return->load(['order', 'items.product', 'user', 'seller'])
            ];
        } elseif ($return->status === ReturnStatus::IN_TRANSIT->value && $user->isSeller()) {
            $return->update(['status' => ReturnStatus::RECEIVED->value]);

            $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_delivery', 'Товар для возврата доставлен в магазин.'));

            Log::info('Return item delivered to seller', [
                'return_id' => $return->id,
                'seller_id' => $user->id,
                'timestamp' => now()
            ]);

            return [
                'success' => true,
                'message' => 'Item delivered to seller successfully.',
                'status' => ReturnStatus::RECEIVED->value,
                'return' => $return->load(['order', 'items.product', 'user', 'seller'])
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Invalid operation for courier return'
            ];
        }
    }

    /**
     * Handle self return QR scan
     */
    private function handleSelfReturnQRScan(OrderReturn $return, $user): array
    {
        if ($return->status === ReturnStatus::APPROVED->value) {
            $return->update(['status' => ReturnStatus::RECEIVED->value]);

            $return->user->notify(new ReturnStatusNotification($return, 'qr_scanned_store', 'Товар для возврата принят в магазине.'));

            Log::info('Return item picked up at store', [
                'return_id' => $return->id,
                'seller_id' => $user->id,
                'timestamp' => now()
            ]);

            return [
                'success' => true,
                'message' => 'Item received at store successfully.',
                'status' => ReturnStatus::RECEIVED->value,
                'return' => $return->load(['order', 'items.product', 'user', 'seller'])
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Invalid operation for self return'
            ];
        }
    }
}
