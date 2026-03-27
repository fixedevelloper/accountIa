<?php

namespace App\Jobs;

use App\Services\GoogleGeminiExtractorService;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDocumentExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(
        public int $documentId,
        public int $userId,
        public string $documentType = 'invoice'
    ) {}

public function handle(GoogleGeminiExtractorService $extractor): void
{
    // 🔒 LOCK atomique : un seul worker peut passer ici
    $updated = Document::where('id', $this->documentId)
        ->where('status', '!=', 'processing')
        ->where('status', '!=', 'extracted')
        ->update([
            'status' => 'processing',
        ]);

    // ❌ Si 0 ligne modifiée → déjà en cours ou terminé
    if ($updated === 0) {
        return;
    }

    $document = Document::findOrFail($this->documentId);

    try {
        $result = $extractor->extractFromFile($document,$this->userId);

        $document->update([
            'status' => 'extracted',
        ]);

    } catch (Throwable $e) {

        Log::error('Extraction failed', [
            'document_id' => $this->documentId,
            'type' => $this->documentType,
            'attempt' => $this->attempts(),
            'error' => $e->getMessage(),
        ]);

        $document->update([
            'status' => 'failed',
        ]);

        throw $e;
    }
}

public function backoff(): array
{
    return [10, 30, 60];
}
}
