<?php

namespace Tests\Feature\Albums;

use App\Models\Album;
use App\Services\Albums\AlbumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class AlbumServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_root_album(): void
    {
        $service = app(AlbumService::class);

        $album = $service->create([
            'slug' => 'viagem-europa',
            'title' => 'Viagem Europa',
            'access_type' => 'public',
        ]);

        $this->assertNotNull($album->id);
        $this->assertNull($album->parent_id);
        $this->assertDatabaseHas('albums', [
            'id' => $album->id,
            'slug' => 'viagem-europa',
            'title' => 'Viagem Europa',
        ]);
    }

    public function test_it_allows_sub_album_under_root(): void
    {
        $service = app(AlbumService::class);

        $root = Album::query()->create([
            'slug' => 'casamento',
            'title' => 'Casamento',
            'access_type' => 'public',
        ]);

        $child = $service->create([
            'slug' => 'casamento-dia-1',
            'title' => 'Dia 1',
            'parent_id' => $root->id,
            'access_type' => 'public',
        ]);

        $this->assertSame($root->id, $child->parent_id);
    }

    public function test_it_rejects_third_level_album(): void
    {
        $service = app(AlbumService::class);

        $root = Album::query()->create([
            'slug' => 'trip',
            'title' => 'Trip',
            'access_type' => 'public',
        ]);

        $child = Album::query()->create([
            'slug' => 'trip-day-1',
            'title' => 'Day 1',
            'parent_id' => $root->id,
            'access_type' => 'public',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only two album levels are allowed.');

        $service->create([
            'slug' => 'trip-day-1-night',
            'title' => 'Night',
            'parent_id' => $child->id,
            'access_type' => 'public',
        ]);
    }

    public function test_it_rejects_setting_itself_as_parent(): void
    {
        $service = app(AlbumService::class);

        $album = Album::query()->create([
            'slug' => 'self-parent',
            'title' => 'Self Parent',
            'access_type' => 'public',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An album cannot be its own parent.');

        $service->update($album, [
            'parent_id' => $album->id,
        ]);
    }
}
