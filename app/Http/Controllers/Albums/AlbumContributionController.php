<?php

namespace App\Http\Controllers\Albums;

use App\Models\Album;
use App\Services\Albums\AlbumContributionService;
use App\Services\Albums\AlbumMediaUploadService;
use App\Support\AlbumUploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AlbumContributionController
{
    public function showInvite(string $album, string $token, AlbumContributionService $contributionService): View
    {
        $model = Album::query()->find($album);
        if ($model === null || $model->is_locked || ! $contributionService->isValidInvite($model, $token)) {
            abort(404);
        }

        return view('albums.contribute.invite', [
            'album' => $model,
            'inviteToken' => $token,
        ]);
    }

    public function requestVerify(Request $request, AlbumContributionService $contributionService): RedirectResponse
    {
        $data = $request->validate([
            'album_id' => ['required', 'uuid'],
            'invite_token' => ['required', 'string', 'size:40'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $album = Album::query()->find($data['album_id']);
        if ($album === null) {
            abort(404);
        }

        try {
            $contributionService->requestEmailVerification(
                $album,
                $data['invite_token'],
                $data['email'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->onlyInput('email');
        }

        return back()->with('contribute_status', 'Enviamos um e-mail com o link de confirmação.');
    }

    public function confirm(string $verify_token, AlbumContributionService $contributionService): View
    {
        try {
            $result = $contributionService->confirmEmail($verify_token);
        } catch (ValidationException $e) {
            return view('albums.contribute.confirm-error', [
                'message' => $e->validator->errors()->first() ?: 'Não foi possível confirmar.',
            ]);
        }

        $contributor = $result['contributor'];
        $contributor->loadMissing('album');

        return view('albums.contribute.confirm-success', [
            'album' => $contributor->album,
            'uploadUrl' => $result['uploadUrl'],
        ]);
    }

    public function showUpload(string $upload_token, AlbumContributionService $contributionService): View
    {
        $contributor = $contributionService->resolveVerifiedContributorForUpload($upload_token);
        if ($contributor === null) {
            abort(404);
        }

        $contributor->loadMissing('album');

        return view('albums.contribute.upload', [
            'contributor' => $contributor,
            'album' => $contributor->album,
            'uploadToken' => $upload_token,
            'effectiveMaxUploadFiles' => AlbumUploadLimits::maxFilesPerHttpRequest(),
            'phpMaxFileUploads' => AlbumUploadLimits::phpMaxFileUploads(),
            'configuredMaxFilesPerBatch' => max(1, (int) config('services.albums.max_files_per_batch')),
        ]);
    }

    public function upload(
        Request $request,
        string $upload_token,
        AlbumContributionService $contributionService,
        AlbumMediaUploadService $uploadService,
    ): RedirectResponse {
        $contributor = $contributionService->resolveVerifiedContributorForUpload($upload_token);
        if ($contributor === null) {
            abort(404);
        }

        $album = $contributor->album;
        if ($album === null) {
            abort(404);
        }

        $maxKb = max(1, (int) ceil(config('services.albums.max_upload_bytes') / 1024));
        $maxFiles = AlbumUploadLimits::maxFilesPerHttpRequest();
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKb],
        ], [], [
            'files' => 'arquivos',
        ]);

        foreach ($request->file('files', []) as $file) {
            try {
                $contributionService->assertContributorMayUploadMore($contributor);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            try {
                $uploadService->store($album, $file, $contributor, 'files');
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }
        }

        return back()->with('contribute_upload_ok', 'Arquivos enviados com sucesso.');
    }
}
