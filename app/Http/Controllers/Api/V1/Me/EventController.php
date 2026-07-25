<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Events\StoreEventRequest;
use App\Http\Requests\Api\V1\Events\UpdateEventRequest;
use App\Models\Event;
use App\Services\Events\EventManagementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EventManagementService $managementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $events = $this->managementService->ownedEventsQuery($request->user())->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => collect($events->items())
                    ->map(fn (Event $event) => $this->managementService->payload($event))
                    ->values(),
                'pagination' => [
                    'currentPage' => $events->currentPage(),
                    'lastPage' => $events->lastPage(),
                    'total' => $events->total(),
                ],
            ],
        ]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->managementService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Evento criado em rascunho.',
            'data' => ['event' => $this->managementService->payload($event)],
        ], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json([
            'success' => true,
            'data' => ['event' => $this->managementService->payload($event)],
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->managementService->update($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Evento atualizado.',
            'data' => ['event' => $this->managementService->payload($event)],
        ]);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->managementService->publish($request->user(), $event);

        return response()->json([
            'success' => true,
            'message' => 'Evento publicado! Sua landing já está no ar.',
            'data' => ['event' => $this->managementService->payload($event)],
        ]);
    }

    public function archive(Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->managementService->archive($event);

        return response()->json([
            'success' => true,
            'message' => 'Evento arquivado.',
            'data' => ['event' => $this->managementService->payload($event)],
        ]);
    }

    public function limits(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->managementService->limitsPayload($request->user()),
        ]);
    }
}
