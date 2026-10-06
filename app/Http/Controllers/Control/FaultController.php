<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Mock\Faults\FaultInjector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET/POST/DELETE /__mock/faults (R12). */
class FaultController extends Controller
{
    public function __construct(private readonly FaultInjector $faults) {}

    public function index(): JsonResponse
    {
        return new JsonResponse(array_map(fn ($f) => $f->toArray(), $this->faults->all()));
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse($this->faults->add($request->all())->toArray(), 201);
    }

    public function destroy(): JsonResponse
    {
        return new JsonResponse(['cleared' => $this->faults->clear()]);
    }
}
