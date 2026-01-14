<?php

namespace App\Http\Controllers\API\v1\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyConvertRequest;
use App\Models\Currency;
use App\Services\CurrencyConverter;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function __construct(
        protected CurrencyConverter $converter
    ) {}

    public function index(): JsonResponse
    {
        $list = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        return response()->json($list);
    }

    public function rates(): JsonResponse
    {
        return response()->json([
            'base'  => $this->converter->getBaseCurrency(),
            'rates' => $this->converter->listRates(),
        ]);
    }

    public function convert(CurrencyConvertRequest $request): JsonResponse
    {
        $data = $request->validated();

        $converted = $this->converter->convert(
            (float) $data['amount'],
            $data['from'] ?? null,
            $data['to']
        );

        return response()->json([
            'amount'   => $converted,
            'currency' => strtoupper($data['to'])
        ]);
    }
}
