<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    /**
     * Helper untuk mendapatkan user yang terautentikasi (termasuk env testing).
     */
    protected function getAuthUser(Request $request): ?User
    {
        $user = $request->user() ?: Auth::user();
        if (!$user && app()->environment('testing')) {
            $userId = $request->header('X-User-Id');
            if ($userId) {
                $user = User::find($userId);
            }
        }
        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | SISI KLIEN (PENGGUNA)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/chat/messages
     * Mengambil riwayat percakapan pengguna aktif dengan admin beserta jumlah pesan belum dibaca.
     */
    public function getMessages(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        $messages = ChatMessage::with(['sender:id,name,email'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $unreadCount = ChatMessage::where('user_id', $user->id)
            ->where('is_admin_reply', true)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $messages,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /api/chat/messages
     * Mengirim pesan dari pengguna ke admin.
     */
    public function sendMessage(SendChatMessageRequest $request): JsonResponse
    {
        $user = $request->getAuthUser();

        $message = ChatMessage::create([
            'user_id' => $user->id,
            'sender_id' => $user->id,
            'is_admin_reply' => false,
            'message' => trim($request->message),
            'is_read' => false,
        ])->load(['sender:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message,
        ], 201);
    }

    /**
     * POST /api/chat/read
     * Menandai semua balasan admin untuk pengguna aktif sebagai telah dibaca.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
            ], 401);
        }

        ChatMessage::where('user_id', $user->id)
            ->where('is_admin_reply', true)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan telah ditandai dibaca.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SISI ADMINISTRATOR (ADMIN INBOX & LIVE SUPPORT)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/admin/chat/conversations
     * Mengambil daftar semua percakapan pengguna, pesan terakhir, dan jumlah unread dari klien.
     */
    public function adminGetConversations(Request $request): JsonResponse
    {
        // Ambil semua user_id unik yang pernah mengirim atau menerima pesan
        $userIds = ChatMessage::select('user_id')
            ->distinct()
            ->pluck('user_id');

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $conversations = [];
        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            if (!$user) {
                continue;
            }

            $lastMsg = ChatMessage::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();

            $unreadCount = ChatMessage::where('user_id', $userId)
                ->where('is_admin_reply', false)
                ->where('is_read', false)
                ->count();

            $conversations[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'last_message' => $lastMsg?->message,
                'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
                'last_is_admin_reply' => (bool) $lastMsg?->is_admin_reply,
                'unread_count' => $unreadCount,
            ];
        }

        // Urutkan berdasarkan last_message_at DESC
        usort($conversations, function ($a, $b) {
            return strcmp($b['last_message_at'] ?? '', $a['last_message_at'] ?? '');
        });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ]);
    }

    /**
     * GET /api/admin/chat/{userId}/messages
     * Mengambil seluruh riwayat pesan dengan satu pengguna spesifik.
     */
    public function adminGetMessages(Request $request, int $userId): JsonResponse
    {
        $client = User::find($userId);
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $messages = ChatMessage::with(['sender:id,name,email'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ],
            'data' => $messages,
        ]);
    }

    /**
     * POST /api/admin/chat/{userId}/reply
     * Admin mengirim balasan ke pengguna tertentu.
     */
    public function adminReply(SendChatMessageRequest $request, int $userId): JsonResponse
    {
        $admin = $request->getAuthUser();

        $client = User::find($userId);
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ], 404);
        }

        $message = ChatMessage::create([
            'user_id' => $client->id,
            'sender_id' => $admin->id,
            'is_admin_reply' => true,
            'message' => trim($request->message),
            'is_read' => false,
        ])->load(['sender:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Balasan berhasil dikirim.',
            'data' => $message,
        ], 201);
    }

    /**
     * POST /api/admin/chat/{userId}/read
     * Admin menandai semua pesan klien untuk pengguna tertentu sebagai telah dibaca.
     */
    public function adminMarkAsRead(Request $request, int $userId): JsonResponse
    {
        ChatMessage::where('user_id', $userId)
            ->where('is_admin_reply', false)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan pengguna telah ditandai dibaca.',
        ]);
    }
}

