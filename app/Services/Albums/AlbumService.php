<?php

namespace App\Services\Albums;

use App\Models\Album;
use InvalidArgumentException;

final class AlbumService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Album
    {
        $parentId = isset($attributes['parent_id']) && is_string($attributes['parent_id'])
            ? $attributes['parent_id']
            : null;

        $this->assertValidParent(parentId: $parentId);

        /** @var Album $album */
        $album = Album::query()->create($attributes);

        return $album;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Album $album, array $attributes): Album
    {
        $parentId = array_key_exists('parent_id', $attributes) && is_string($attributes['parent_id'])
            ? $attributes['parent_id']
            : null;

        $this->assertValidParent($album, $parentId);

        $album->fill($attributes);
        $album->save();

        return $album->refresh();
    }

    private function assertValidParent(?Album $album = null, ?string $parentId = null): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($album !== null && $album->id === $parentId) {
            throw new InvalidArgumentException('An album cannot be its own parent.');
        }

        $parent = Album::query()->with('parent')->find($parentId);
        if ($parent === null) {
            throw new InvalidArgumentException('Parent album not found.');
        }

        if ($parent->parent_id !== null) {
            throw new InvalidArgumentException('Only two album levels are allowed.');
        }
    }
}
