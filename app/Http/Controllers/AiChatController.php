<?php

namespace App\Http\Controllers;

use App\Enums\AiTask;
use App\Http\Requests\AiChat\ChatCompletionRequest;
use App\Http\Requests\AiChat\GenerateImageRequest;
use App\Http\Requests\AiChat\TranscribeAudioRequest;
use App\Services\AiChatCatalogService;
use App\Services\AiImageGenerationService;
use App\Services\AiTranscriptionService;
use App\Services\NeuronAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

final class AiChatController extends Controller
{
    public function index(): View
    {
        return view('chat', [
            'chatConfig' => [
                'optionsUrl' => route('api.ai.chat-options'),
                'chatUrl' => route('api.ai.chat'),
                'transcribeUrl' => route('api.ai.transcribe'),
                'imagesUrl' => route('api.ai.images'),
            ],
        ]);
    }

    public function options(AiChatCatalogService $catalog): JsonResponse
    {
        return response()->json($catalog->buildOptionsPayload());
    }

    public function chat(ChatCompletionRequest $request, NeuronAIService $ai): JsonResponse
    {
        /** @var array{provider: string, model: string, message?: string|null, system?: string|null, images?: array<int, array{data: string, mime: string}>} $data */
        $data = $request->validated();

        $images = [];
        foreach ($data['images'] ?? [] as $img) {
            $images[] = ['data' => $img['data'], 'mime' => $img['mime']];
        }

        $result = $ai->completeDirect(
            providerKey: $data['provider'],
            model: $data['model'],
            task: AiTask::ChatDefault,
            userPrompt: (string) ($data['message'] ?? ''),
            systemPromptOverride: $data['system'] ?? null,
            images: $images,
        );

        $status = $result->success ? 200 : 503;

        return response()->json($result->toArray(config('app.debug')), $status);
    }

    public function transcribe(TranscribeAudioRequest $request, AiTranscriptionService $transcription): JsonResponse
    {
        /** @var array{audio: UploadedFile, engine: string} $data */
        $data = $request->validated();

        $out = $transcription->transcribe($data['audio'], $data['engine']);

        $status = $out['ok'] ? 200 : 422;

        return response()->json($out, $status);
    }

    public function generateImage(GenerateImageRequest $request, AiImageGenerationService $images): JsonResponse
    {
        /** @var array{prompt: string, size?: string|null, response_format?: string|null} $data */
        $data = $request->validated();

        $out = $images->generate(
            prompt: $data['prompt'],
            size: $data['size'] ?? null,
            responseFormat: $data['response_format'] ?? null,
        );

        $status = $out['ok'] ? 200 : 422;

        return response()->json($out, $status);
    }
}
