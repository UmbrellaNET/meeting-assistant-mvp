<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Put in: apps/api/app/Services/GeminiClient.php
// Calls the Gemini API directly from the API service (not through the AI worker).
class GeminiClient
{
    /** Must match vector(768) in the transcript_chunks migration. */
    public const DIMENSIONS = 768;

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function configured(): bool
    {
        return (string) config('services.gemini.key') !== '';
    }

    /**
     * @param  string[]  $texts
     * @return array<int, float[]>  one vector per text, in the same order
     */
    public function embed(array $texts, string $taskType): array
    {
        $texts = array_values($texts);
        if ($texts === []) {
            return [];
        }

        $model = (string) config('services.gemini.embedding_model', 'gemini-embedding-001');
        $vectors = [];

        foreach (array_chunk($texts, 50) as $batch) {
            $response = $this->request()->post(self::BASE."/models/{$model}:batchEmbedContents", [
                'requests' => array_map(fn (string $text) => [
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                    'taskType' => $taskType,
                    'outputDimensionality' => self::DIMENSIONS,
                ], $batch),
            ]);
            $this->assertOk($response, 'embedding');

            $embeddings = $response->json('embeddings') ?? [];
            if (count($embeddings) !== count($batch)) {
                throw new RuntimeException('Gemini returned an unexpected number of embeddings.');
            }
            foreach ($embeddings as $embedding) {
                $vectors[] = array_map('floatval', $embedding['values'] ?? []);
            }
        }

        return $vectors;
    }

    /** @return float[] */
    public function embedOne(string $text, string $taskType): array
    {
        return $this->embed([$text], $taskType)[0];
    }

    public function generate(string $system, string $prompt, float $temperature = 0.2, int $maxTokens = 4096): string
    {
        $model = (string) config('services.gemini.chat_model', 'gemini-2.5-flash');

        $response = $this->request()->post(self::BASE."/models/{$model}:generateContent", [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
        ]);
        $this->assertOk($response, 'generation');

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $text = trim(collect($parts)->pluck('text')->filter()->implode(''));
        if ($text === '') {
            throw new RuntimeException('Gemini returned an empty answer.');
        }

        return $text;
    }

    /** Format a vector for pgvector, e.g. "[0.1,0.2,0.3]". */
    public static function vectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn ($value) => (string) (float) $value, $vector)).']';
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->timeout(90);
    }

    private function assertOk(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        // The HTTP status is kept as the exception code so callers can tell "busy" (429) from other failures.
        throw new RuntimeException(
            "Gemini {$what} request failed: HTTP {$response->status()} ".mb_substr($response->body(), 0, 300),
            $response->status()
        );
    }
}
