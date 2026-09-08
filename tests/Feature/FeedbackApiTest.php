<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'email' => 'restu@dev.local',
            'name' => 'Restu Putra Anggara',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_authenticated_user_can_submit_feedback(): void
    {
        $payload = [
            'type' => 'feature',
            'rating' => 5,
            'message' => 'Tolong tambahkan fitur grafik tren bulanan yang lebih detail.',
        ];

        $res = $this->actingAs($this->user)->postJson('/api/feedback', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->user->id,
                    'type' => 'feature',
                    'rating' => 5,
                    'message' => 'Tolong tambahkan fitur grafik tren bulanan yang lebih detail.',
                ],
            ]);

        $this->assertDatabaseHas('feedbacks', [
            'user_id' => $this->user->id,
            'type' => 'feature',
            'rating' => 5,
        ]);
    }

    public function test_guest_can_submit_anonymous_feedback(): void
    {
        $payload = [
            'type' => 'bug',
            'rating' => 4,
            'message' => 'Tampilan tombol di mobile layar kecil agak mepet.',
        ];

        $res = $this->postJson('/api/feedback', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => null,
                    'type' => 'bug',
                ],
            ]);

        $this->assertDatabaseHas('feedbacks', [
            'user_id' => null,
            'type' => 'bug',
        ]);
    }

    public function test_feedback_validation_errors(): void
    {
        $res = $this->postJson('/api/feedback', [
            'type' => 'invalid_type',
            'message' => 'abc', // Kurang dari 5 karakter
            'rating' => 10,     // Maksimal 5
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message', 'rating']);
    }
}
