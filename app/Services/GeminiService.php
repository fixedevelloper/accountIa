<?php

namespace App\Services;

use Gemini\Data\Content;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $model = 'gemini-2.5-flash'; // ✅ Modèle officiel

    public function generateResponse(string $prompt, array $history = []): array
    {
        try {
            // ✅ FORMAT OFFICIEL avec Content::parse()
            $chatHistory = $this->buildChatHistory($history);

            $chat = Gemini::generativeModel($this->model)
                ->startChat(history: $chatHistory);

            $response = $chat->sendMessage($prompt);

            return [
                'success' => true,
                'text' => $response->text(),
                'usage' => null
            ];

        } catch (\Throwable $e) {
            Log::error('GeminiService', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'IA indisponible'];
        }
    }

    /**
     * ✅ CONSTRUCTEUR HISTORIQUE OFFICIEL
     * @param array $history
     * @return array
     */
    private function buildChatHistory(array $history): array
    {
        $chatHistory = [];

        foreach ($history as $msg) {
            $type = $msg['type'] ?? 'ai';

            if ($type === 'user') {
                $chatHistory[] = Content::parse(part: $msg['text']);
            } else {
                $chatHistory[] = Content::parse(
                    part: $msg['text'],
                    role: Role::MODEL
                );
            }
        }

        return $chatHistory;
    }

    public function streamResponse(string $prompt, callable $callback): void
    {
        try {
            Gemini::generativeModel($this->model)
                ->streamGenerateContent($prompt, $callback);
        } catch (\Throwable $e) {
            Log::error('Stream error: ' . $e->getMessage());
        }
    }
}
