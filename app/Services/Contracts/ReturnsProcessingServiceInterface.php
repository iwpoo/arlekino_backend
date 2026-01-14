<?php

namespace App\Services\Contracts;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\Request;

interface ReturnsProcessingServiceInterface
{
    /**
     * Process return creation
     *
     * @param Request $request
     * @return array
     */
    public function processReturnCreation(Request $request): array;

    /**
     * Process return approval
     *
     * @param OrderReturn $return
     * @param Request $request
     * @return array
     */
    public function processReturnApproval(OrderReturn $return, Request $request): array;

    /**
     * Process return rejection
     *
     * @param OrderReturn $return
     * @param Request $request
     * @return array
     */
    public function processReturnRejection(OrderReturn $return, Request $request): array;

    /**
     * Process return status updates
     *
     * @param OrderReturn $return
     * @param string $newStatus
     * @return array
     */
    public function processReturnStatusUpdate(OrderReturn $return, string $newStatus): array;

    /**
     * Get user's preferred currency
     *
     * @param mixed $user
     * @return string
     */
    public function getUserPreferredCurrency(mixed $user): string;

    /**
     * Convert return prices to specified currency
     *
     * @param mixed $returns
     * @param string $toCurrency
     * @return void
     */
    public function convertReturnPrices(mixed $returns, string $toCurrency): void;

    /**
     * Check if order is eligible for return
     *
     * @param Order $order
     * @return array
     */
    public function checkReturnEligibility(Order $order): array;

    /**
     * Get eligible items for return
     *
     * @param Order $order
     * @return mixed
     */
    public function getEligibleItems(Order $order): mixed;

    /**
     * Generate QR code for return
     *
     * @param OrderReturn $return
     * @return array
     */
    public function generateReturnQR(OrderReturn $return): array;

    /**
     * Regenerate QR code for return
     *
     * @param OrderReturn $return
     * @return array
     */
    public function regenerateReturnQR(OrderReturn $return): array;

    /**
     * Scan QR code and process return status
     *
     * @param string $qrCode
     * @param Request $request
     * @return array
     */
    public function scanReturnQR(string $qrCode, Request $request): array;
}