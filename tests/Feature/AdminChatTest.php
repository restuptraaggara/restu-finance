<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::create([
            'email' => 'client@restu.test',
            'name' => 'Budi Santoso',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        $this->admin = User::create([
            'email' => 'admin@restu.test',
            'name' => 'Administrator Restu',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);
    }

    public function test_client_can_send_chat_message(): void
    {
        $payload = [
            'message' => 'Halo admin, bagaimana cara backup data transaksi saya?',
        ];

        $res = $this->actingAs($this->client)->postJson('/api/chat/messages', $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->client->id,
                    'sender_id' => $this->client->id,
                    'is_admin_reply' => false,
                    'message' => 'Halo admin, bagaimana cara backup data transaksi saya?',
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Halo admin, bagaimana cara backup data transaksi saya?',
        ]);
    }

    public function test_client_cannot_send_empty_chat_message(): void
    {
        $res = $this->actingAs($this->client)->postJson('/api/chat/messages', [
            'message' => '',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_client_can_get_chat_history_and_unread_count(): void
    {
        // Client message
        ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Tanya fitur ekspor CSV',
            'is_read' => true,
        ]);

        // Unread Admin reply
        ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->admin->id,
            'is_admin_reply' => true,
            'message' => 'Fitur ekspor CSV ada di halaman Pengaturan dan Riwayat Transaksi ya.',
            'is_read' => false,
        ]);

        $res = $this->actingAs($this->client)->getJson('/api/chat/messages');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 1,
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_client_can_mark_admin_replies_as_read(): void
    {
        $msg = ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->admin->id,
            'is_admin_reply' => true,
            'message' => 'Pesan balasan admin yang belum dibaca.',
            'is_read' => false,
        ]);

        $res = $this->actingAs($this->client)->postJson('/api/chat/read');

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue($msg->fresh()->is_read);
    }

    public function test_non_admin_cannot_access_admin_chat_endpoints(): void
    {
        // Regular client tries to view admin conversations
        $res = $this->actingAs($this->client)->getJson('/api/admin/chat/conversations');
        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akses ditolak. Khusus administrator.',
            ]);

        // Regular client tries to reply
        $replyRes = $this->actingAs($this->client)->postJson("/api/admin/chat/{$this->client->id}/reply", [
            'message' => 'Percobaan membalas pesan',
        ]);
        $replyRes->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_chat_endpoints(): void
    {
        $res = $this->getJson('/api/admin/chat/conversations');
        $res->assertStatus(403);
    }

    public function test_admin_can_view_all_conversations(): void
    {
        ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Butuh bantuan akun',
            'is_read' => false,
        ]);

        $res = $this->actingAs($this->admin)->getJson('/api/admin/chat/conversations');

        $res->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment([
                'user_id' => $this->client->id,
                'user_name' => 'Budi Santoso',
                'user_email' => 'client@restu.test',
                'last_message' => 'Butuh bantuan akun',
                'unread_count' => 1,
            ]);
    }

    public function test_admin_can_view_specific_user_messages(): void
    {
        ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Pertanyaan untuk admin',
        ]);

        $res = $this->actingAs($this->admin)->getJson("/api/admin/chat/{$this->client->id}/messages");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'client' => [
                    'id' => $this->client->id,
                    'name' => 'Budi Santoso',
                ],
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_reply_to_user_message(): void
    {
        ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Tolong bantu reset password',
        ]);

        $payload = [
            'message' => 'Halo Budi, Anda dapat mengubah kata sandi di menu Pengaturan Akun.',
        ];

        $res = $this->actingAs($this->admin)->postJson("/api/admin/chat/{$this->client->id}/reply", $payload);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->client->id,
                    'sender_id' => $this->admin->id,
                    'is_admin_reply' => true,
                    'message' => 'Halo Budi, Anda dapat mengubah kata sandi di menu Pengaturan Akun.',
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $this->client->id,
            'sender_id' => $this->admin->id,
            'is_admin_reply' => true,
        ]);
    }

    public function test_admin_can_mark_client_messages_as_read(): void
    {
        $msg = ChatMessage::create([
            'user_id' => $this->client->id,
            'sender_id' => $this->client->id,
            'is_admin_reply' => false,
            'message' => 'Pesan dari klien belum dibaca admin',
            'is_read' => false,
        ]);

        $res = $this->actingAs($this->admin)->postJson("/api/admin/chat/{$this->client->id}/read");

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue($msg->fresh()->is_read);
    }

    public function test_artisan_make_admin_command(): void
    {
        $this->assertFalse($this->client->is_admin);

        // Promote to admin
        $this->artisan('user:make-admin', ['email' => $this->client->email])
            ->assertExitCode(0);

        $this->assertTrue($this->client->fresh()->is_admin);

        // Revoke admin status
        $this->artisan('user:make-admin', ['email' => $this->client->email, '--revoke' => true])
            ->assertExitCode(0);

        $this->assertFalse($this->client->fresh()->is_admin);
    }
}

