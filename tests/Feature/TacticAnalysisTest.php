<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateTacticalAdviceJob;
use Database\Seeders\GameSenseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TacticAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_instant_cache_hit_when_prompt_hash_already_exists(): void
    {
        $this->seed(GameSenseSeeder::class);

        $response = $this->postJson('/api/tactics/analyze', [
            'game_id' => 1,
            'category_id' => 1,
            'game_phase' => 'Mid Game (Industrialization / Act 2)',
            'missing_resource' => 'Coal & Iron (+58% above base price)',
            'user_query' => 'My Construction Sectors entered a severe deficit after switching to Iron-Frame Buildings. Coal and Iron prices skyrocketed and my gold reserves will hit zero in 10 weeks.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('cached', true);
    }

    public function test_dispatches_job_to_laravel_queue_on_cache_miss_in_async_mode(): void
    {
        $this->seed(GameSenseSeeder::class);
        Queue::fake();

        config(['queue.default' => 'database']);

        $response = $this->postJson('/api/tactics/analyze', [
            'game_id' => 2,
            'category_id' => 3,
            'game_phase' => 'Late Game (Endgame / Pantheon / Honour)',
            'missing_resource' => 'Soul & Masks',
            'user_query' => 'How do I dodge Absolute Radiance triple beam attacks on the central platform?',
            'dispatch_mode' => 'async',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('cached', false);

        Queue::assertPushed(GenerateTacticalAdviceJob::class);
    }
}
