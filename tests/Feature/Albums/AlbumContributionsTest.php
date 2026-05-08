<?php

namespace Tests\Feature\Albums;

use App\Jobs\Albums\ProcessAlbumPhotoJob;
use App\Jobs\Albums\SendAlbumContributionDigestJob;
use App\Livewire\Albums\AlbumDetailPage;
use App\Mail\AlbumContributionVerifyMail;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Contributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AlbumContributionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'mail.from.address' => 'admin@example.test',
            'services.albums.contribution_notify_debounce_seconds' => 600,
        ]);
    }

    private function albumWithInvite(): Album
    {
        return Album::query()->create([
            'slug' => 'contrib-'.Str::slug(Str::random(8)),
            'title' => 'Contrib Album',
            'access_type' => 'public',
            'contribution_invite_token' => Str::random(40),
        ]);
    }

    public function test_invite_page_404_for_invalid_token(): void
    {
        $album = $this->albumWithInvite();

        $this->get(route('albums.contribute.invite', [
            'album' => $album->id,
            'token' => Str::random(40),
        ]))->assertNotFound();
    }

    public function test_verify_request_sends_confirmation_mail(): void
    {
        Mail::fake();

        $album = $this->albumWithInvite();

        $this->from(route('albums.contribute.invite', [
            'album' => $album->id,
            'token' => $album->contribution_invite_token,
        ]))->post(route('albums.contribute.verify'), [
            'album_id' => $album->id,
            'invite_token' => $album->contribution_invite_token,
            'email' => 'guest@example.test',
        ])->assertRedirect();

        Mail::assertSent(AlbumContributionVerifyMail::class, function (AlbumContributionVerifyMail $mail): bool {
            return $mail->hasTo('guest@example.test');
        });
    }

    public function test_confirm_fails_when_verify_expired(): void
    {
        $album = $this->albumWithInvite();

        $contributor = Contributor::query()->create([
            'album_id' => $album->id,
            'email' => 'late@example.test',
            'email_verified' => false,
            'verify_token' => Str::random(40),
            'verify_expires_at' => now()->subHour(),
            'upload_token' => null,
            'upload_expires_at' => null,
        ]);

        $this->get(route('albums.contribute.confirm', [
            'verify_token' => (string) $contributor->verify_token,
        ]))->assertOk()->assertSee('expirou', false);
    }

    public function test_confirm_fails_when_token_reused(): void
    {
        $album = $this->albumWithInvite();

        $token = Str::random(40);

        Contributor::query()->create([
            'album_id' => $album->id,
            'email' => 'twice@example.test',
            'email_verified' => true,
            'verify_token' => null,
            'verify_expires_at' => null,
            'upload_token' => Str::random(40),
            'upload_expires_at' => now()->addDay(),
        ]);

        $this->get(route('albums.contribute.confirm', [
            'verify_token' => $token,
        ]))->assertOk()->assertSee('inválido', false);
    }

    public function test_confirm_happy_path_then_upload_creates_contributor_media(): void
    {
        Queue::fake();

        $album = $this->albumWithInvite();

        $verifyToken = Str::random(40);

        Contributor::query()->create([
            'album_id' => $album->id,
            'email' => 'ok@example.test',
            'email_verified' => false,
            'verify_token' => $verifyToken,
            'verify_expires_at' => now()->addDay(),
            'upload_token' => null,
            'upload_expires_at' => null,
        ]);

        $this->get(route('albums.contribute.confirm', ['verify_token' => $verifyToken]))
            ->assertOk()
            ->assertSee('E-mail confirmado', false);

        $contributor = Contributor::query()->where('email', 'ok@example.test')->first();
        $this->assertNotNull($contributor);
        $this->assertTrue($contributor->email_verified);
        $this->assertNotNull($contributor->upload_token);

        $uploadToken = (string) $contributor->upload_token;

        $file = UploadedFile::fake()->image('from-guest.jpg', 400, 300);

        $this->post(route('albums.contribute.upload', ['upload_token' => $uploadToken]), [
            'files' => [$file],
        ])->assertRedirect();

        $media = AlbumMedia::query()->where('album_id', $album->id)->first();
        $this->assertNotNull($media);
        $this->assertSame('contributor', $media->uploaded_by);
        $this->assertSame($contributor->id, $media->contributor_id);

        Queue::assertPushed(ProcessAlbumPhotoJob::class);
        Queue::assertPushed(SendAlbumContributionDigestJob::class, function (SendAlbumContributionDigestJob $job) use ($album): bool {
            return $job->albumId === $album->id && $job->queue === 'notifications';
        });
    }

    public function test_multi_file_upload_dispatches_single_digest_job(): void
    {
        Queue::fake();

        $album = $this->albumWithInvite();

        $uploadToken = Str::random(40);

        Contributor::query()->create([
            'album_id' => $album->id,
            'email' => 'multi@example.test',
            'email_verified' => true,
            'verify_token' => null,
            'verify_expires_at' => null,
            'upload_token' => $uploadToken,
            'upload_expires_at' => now()->addDay(),
        ]);

        $this->post(route('albums.contribute.upload', ['upload_token' => $uploadToken]), [
            'files' => [
                UploadedFile::fake()->image('a.jpg', 80, 80),
                UploadedFile::fake()->image('b.jpg', 80, 80),
            ],
        ])->assertRedirect();

        Queue::assertPushed(SendAlbumContributionDigestJob::class, 1);
    }

    public function test_upload_404_when_token_revoked(): void
    {
        $album = $this->albumWithInvite();

        $uploadToken = Str::random(40);

        Contributor::query()->create([
            'album_id' => $album->id,
            'email' => 'rev@example.test',
            'email_verified' => true,
            'verify_token' => null,
            'verify_expires_at' => null,
            'upload_token' => $uploadToken,
            'upload_expires_at' => now()->addDay(),
        ]);

        Contributor::query()->where('album_id', $album->id)->update([
            'upload_token' => null,
            'upload_expires_at' => null,
        ]);

        $this->post(route('albums.contribute.upload', ['upload_token' => $uploadToken]), [
            'files' => [UploadedFile::fake()->image('x.jpg', 40, 40)],
        ])->assertNotFound();
    }

    public function test_guest_cannot_open_hub_album_detail(): void
    {
        $album = $this->albumWithInvite();

        $this->get(route('albums.hub.show', ['album' => $album->id]))
            ->assertRedirect(route('login'));
    }

    public function test_contribution_upload_only_creates_media_for_invite_album(): void
    {
        $albumA = $this->albumWithInvite();
        $albumB = $this->albumWithInvite();

        $uploadToken = Str::random(40);

        Contributor::query()->create([
            'album_id' => $albumA->id,
            'email' => 'a@example.test',
            'email_verified' => true,
            'verify_token' => null,
            'verify_expires_at' => null,
            'upload_token' => $uploadToken,
            'upload_expires_at' => now()->addDay(),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('albums.contribute.upload', ['upload_token' => $uploadToken]), [
            'files' => [UploadedFile::fake()->image('x.jpg', 40, 40)],
        ])->assertRedirect();

        $this->assertSame(1, AlbumMedia::query()->where('album_id', $albumA->id)->count());
        $this->assertSame(0, AlbumMedia::query()->where('album_id', $albumB->id)->count());
    }

    public function test_hub_can_generate_contribution_invite(): void
    {
        $user = User::factory()->create();
        $album = Album::query()->create([
            'slug' => 'hub-contrib',
            'title' => 'Hub Contrib',
            'access_type' => 'public',
        ]);

        Livewire::actingAs($user)
            ->test(AlbumDetailPage::class, ['album' => $album])
            ->call('generateContributionInvite')
            ->assertHasNoErrors();

        $album->refresh();
        $this->assertNotNull($album->contribution_invite_token);
    }
}
