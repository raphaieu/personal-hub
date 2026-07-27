<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Events\FlyerDraftRequest;
use App\Models\Event;
use App\Services\Events\EventFlyerService;
use App\Services\Events\EventManagementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class FlyerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EventFlyerService $flyerService,
        private readonly EventManagementService $managementService,
    ) {}

    /**
     * Upload do flyer → evento em rascunho + job de extração IA na fila.
     */
    public function store(FlyerDraftRequest $request): JsonResponse
    {
        $event = $this->flyerService->createDraftFromFlyer(
            $request->user(),
            $request->file('flyer'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Flyer recebido! Estamos analisando e montando sua landing...',
            'data' => ['event' => $this->managementService->payload($event)],
        ], 201);
    }

    /**
     * Polling do processamento IA (front consulta até ready|failed).
     */
    public function status(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'success' => true,
            'data' => [
                'aiStatus' => $event->ai_status,
                'done' => in_array($event->ai_status, ['ready', 'failed'], true),
            ],
        ]);
    }
}
