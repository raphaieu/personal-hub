<?php


namespace App\Http\Controllers\Api\V1\Events;

use App\Enums\Events\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\EventPublicConfigService;
use App\Services\Events\EventRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PublicEventController extends Controller
{
    public function __construct(
        private readonly EventPublicConfigService $configService,
        private readonly EventRegistrationService $registrationService,
    ) {}

    public function config(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();

        if ($event === null || in_array($event->status, [EventStatus::Draft, EventStatus::Archived], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Evento não encontrado.',
            ], 404);
        }

        $payload = $this->configService->build($event, $request->query('ref'));

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function register(Request $request, string $slug): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->first();

        if ($event === null || in_array($event->status, [EventStatus::Draft, EventStatus::Archived], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Evento não encontrado.',
            ], 404);
        }

        $payload = $this->configService->build($event, $request->header('X-Ref-Token'));

        if (! ($payload['registration']['open'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'As inscrições para este evento estão encerradas.',
            ], 403);
        }

        try {
            $guest = $this->registrationService->createFromPublicRequest($event, $request);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Verifique os campos e tente novamente.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recebemos sua inscrição! Enviamos um e-mail para '.$guest->email.' com um link para confirmar seu interesse e receber o ingresso. Verifique caixa de entrada e spam.',
        ], 201);
    }
}
