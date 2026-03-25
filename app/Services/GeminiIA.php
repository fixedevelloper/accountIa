<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiIA
{
    protected string $apiKey;
    protected string $endpoint;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key'); // mettre ta clé dans .env
        $this->endpoint = config('services.gemini.endpoint'); // URL de l'API
    }

    /**
     * Envoyer un prompt à l'IA et récupérer la réponse
     */
    public function ask(string $prompt, array $options = []): string
    {
        // Exemple simple avec un POST JSON
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post($this->endpoint, array_merge([
            'prompt' => $prompt,
            'max_tokens' => 500,
        ], $options));

        if ($response->failed()) {
            throw new \Exception('Erreur lors de la requête GeminiIA : ' . $response->body());
        }

        $data = $response->json();

        // Adapter selon la structure de la réponse de Gemini
        return $data['response'] ?? ($data['choices'][0]['text'] ?? '');
    }
}
