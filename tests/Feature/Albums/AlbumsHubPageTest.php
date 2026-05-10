<?php

namespace Tests\Feature\Albums;

use App\Livewire\Albums\HubPage;
use App\Models\Album;
use App\Models\AlbumLockout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AlbumsHubPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_albums_hub(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('albums.hub'));

        $response
            ->assertOk()
            ->assertSee('Hub Albums')
            ->assertSee('Novo álbum');
    }

    public function test_guest_is_redirected_from_albums_hub(): void
    {
        $this->get(route('albums.hub'))->assertRedirect();
    }

    public function test_livewire_can_create_root_album(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formTitle', 'Viagem')
            ->set('formSlug', 'viagem')
            ->set('formAccessType', 'public')
            ->call('saveAlbum')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('albums', [
            'title' => 'Viagem',
            'slug' => 'viagem',
            'parent_id' => null,
        ]);
    }

    public function test_livewire_can_create_child_album_under_root(): void
    {
        $user = User::factory()->create();
        $root = Album::query()->create([
            'title' => 'Raiz',
            'slug' => 'raiz',
            'access_type' => 'public',
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formTitle', 'Filho')
            ->set('formSlug', 'filho')
            ->set('formParentId', $root->id)
            ->set('formAccessType', 'public')
            ->call('saveAlbum')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('albums', [
            'title' => 'Filho',
            'slug' => 'filho',
            'parent_id' => $root->id,
        ]);
    }

    public function test_livewire_rejects_third_level_album(): void
    {
        $user = User::factory()->create();
        $root = Album::query()->create([
            'title' => 'Raiz',
            'slug' => 'raiz',
            'access_type' => 'public',
        ]);
        $child = Album::query()->create([
            'title' => 'Filho',
            'slug' => 'filho',
            'access_type' => 'public',
            'parent_id' => $root->id,
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formTitle', 'Neto')
            ->set('formSlug', 'neto')
            ->set('formParentId', $child->id)
            ->set('formAccessType', 'public')
            ->call('saveAlbum')
            ->assertHasErrors(['formParentId']);
    }

    public function test_livewire_can_create_token_album_with_expiration(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->set('formTitle', 'Privado por token')
            ->set('formSlug', 'privado-token')
            ->set('formAccessType', 'token')
            ->set('formToken', 'meu-token-seguro')
            ->set('formTokenExpiresAt', '2030-01-01T10:00')
            ->call('saveAlbum')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('albums', [
            'slug' => 'privado-token',
            'access_type' => 'token',
            'token' => 'meu-token-seguro',
        ]);
    }

    public function test_livewire_can_unlock_active_lockout(): void
    {
        $user = User::factory()->create();
        $album = Album::query()->create([
            'title' => 'Raiz',
            'slug' => 'raiz',
            'access_type' => 'public',
        ]);

        $lockout = AlbumLockout::query()->create([
            'album_id' => $album->id,
            'ip' => '127.0.0.1',
            'locked_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(HubPage::class)
            ->call('unlockLockout', $lockout->id)
            ->assertHasNoErrors();

        $this->assertNotNull($lockout->fresh()?->unlocked_at);
    }
}
