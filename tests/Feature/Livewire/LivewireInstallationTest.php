<?php

namespace Tests\Feature\Livewire;

use Tests\TestCase;

final class LivewireInstallationTest extends TestCase
{
    public function test_livewire_package_is_available_in_container(): void
    {
        $this->assertTrue(class_exists(\Livewire\Livewire::class));
        $this->assertNotNull(app('livewire'));
    }
}
