<?php

use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('loads the bundled background when no uploaded background is available', function () {
    $html = Blade::render('<x-page-wrapper page="Background test">Test content</x-page-wrapper>');

    expect($html)
        ->toContain("url('".asset('images/zijpalm_background.jpg')."')")
        ->and($html)
        ->toContain('min-h-[calc(100vh_-_4rem)] bg-cover !bg-center bg-no-repeat');
});

it('copies and seeds the bundled background image', function () {
    Storage::fake('public');

    $this->seed(ContentSeeder::class);

    Storage::disk('public')->assertExists('content/zijpalm_background.jpg');
    $this->assertDatabaseHas('contents', [
        'name' => 'background',
        'filePath' => 'content/zijpalm_background.jpg',
    ]);
});
