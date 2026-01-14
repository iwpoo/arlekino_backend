<?php

namespace App\Services\Contracts;

use App\Models\Order;
use App\Services\CurrencyConverter;
use Illuminate\Http\Request;

interface OrderProcessingServiceInterface
{
    /**
     * @param array $validatedData
     * @param Request $request
     * @return array
     */
    public function processOrderCreation(array $validatedData, Request $request): array;

    /**
     * @param Order $order
     * @param array $validatedData
     * @param Request $request
     * @return array
     */
    public function processOrderStatusUpdate(Order $order, array $validatedData, Request $request): array;

    /**
     * Aggregate order status based on seller orders
     *
     * @param Order $order
     * @return void
     */
    public function aggregateOrderStatus(Order $order): void;

    /**
     * Calculate discounted price for a product
     *
     * @param mixed $product
     * @return float
     */
    public function calculateDiscountedPrice(mixed $product): float;

    /**
     * Format delivery point
     *
     * @param array $point
     * @return string
     */
    public function formatDeliveryPoint(array $point): string;

    /**
     * Process payment for an order
     *
     * @param Order $order
     * @param string $paymentMethod
     * @param int|null $cardId
     * @return array
     */
    public function processPayment(Order $order, string $paymentMethod, ?int $cardId): array;

    /**
     * Get user's preferred currency
     *
     * @param mixed $user
     * @return string
     */
    public function getUserPreferredCurrency(mixed $user): string;

    /**
     * Convert order prices to specified currency
     *
     * @param mixed $orders
     * @param string $toCurrency
     * @return void
     */
    public function convertOrderPrices(mixed $orders, string $toCurrency): void;

    /**
     * Get QR allowed statuses
     *
     * @return array
     */
    public function getQRAllowedStatuses(): array;

    /**
     * Get all allowed statuses
     *
     * @return array
     */
    public function getAllowedStatusesAll(): array;

    /**
     * Get currency converter
     *
     * @return CurrencyConverter
     */
    public function getCurrencyConverter(): CurrencyConverter;
}
