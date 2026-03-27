<?php
// app/Services/GoogleGeminiExtractorService.php

namespace App\Services;


use App\Models\Message;
use App\Models\Project;
use finfo;
use Gemini\Data\Blob;
use Gemini\Data\Content;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
use Gemini\Laravel\Facades\Gemini;
use App\Models\Document;
use App\Models\DocumentExtraction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GoogleGeminiExtractorService
{
    private $gemini;
    private $model = 'gemini-2.5-flash';
    public function __construct(Gemini $gemini)
    {
        $this->gemini = $gemini;
    }

    public function extractFromFile(Document $document, $userId)
    {
        try {
            $fullPath = Storage::disk('public')->path($document->file_path);

            if (!file_exists($fullPath)) {
                $document->update(['status' => 'failed']);
                throw new \Exception('File not found: ' . $document->file_path);
            }

            // Extraction via Gemini
            $extractionData = $this->extractWithGemini(
                $fullPath,
                $document->company_id,
                $document->type
            );

            // Création extraction
            $extraction = $document->extractions()->create($extractionData);

            // Projet + messages
            $prompt = $this->buildExtractionPrompt($document->type, $document->company_id);

            $this->initProjet(
                $document,
                $userId,
                $prompt,
                json_encode($extractionData)
            );

            // Classification safe
            $this->classifyAccount($extraction);

            // Mise à jour status document
            $document->update(['status' => 'extracted']);

            return $extractionData;

        } catch (\Throwable $e) {
            // Capture toutes les erreurs (exceptions et erreurs fatales)
            Log::error('Document extraction failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mettre le document en failed
            $document->update(['status' => 'failed']);

            // Rejeter l'exception pour le Job si besoin
            throw $e;
        }
    }

    private function extractWithGemini(string $filePath, int $companyId, string $documentType)
    {
        $prompt = $this->buildExtractionPrompt($documentType, $companyId);

        // ✅ Lire le fichier UNE seule fois
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            Log::error("Impossible de lire le fichier", ['file' => $filePath]);
            throw new \Exception('Unable to read file');
        }

        // ✅ Détecter MIME
        $mimeType = $this->detectMimeType($filePath);
        Log::info("Fichier prêt pour extraction Gemini", [
            'file' => $filePath,
            'size' => strlen($fileContent),
            'mime' => $mimeType->value ?? (string)$mimeType,
            'document_type' => $documentType
        ]);

        // ✅ Appel Gemini
        try {
            $response = Gemini::generativeModel('gemini-2.5-flash')
                ->generateContent([
                    $prompt,
                    new Blob(
                        mimeType: $mimeType,
                    data: base64_encode($fileContent)
                )
            ])
            ->text();

        Log::info("Réponse brute Gemini reçue", [
            'document_type' => $documentType,
            'response_length' => strlen($response),
            'sample' => substr($response, 0, 500) // premiers 500 caractères pour debug
        ]);

    } catch (\Exception $e) {
            Log::error("Erreur lors de l'appel Gemini", [
                'file' => $filePath,
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        // ✅ Nettoyer la réponse JSON
        $cleanJson = $this->extractJson($response);
        Log::info("Réponse Gemini nettoyée", [
            'clean_json' => substr($cleanJson, 0, 500)
        ]);

        $jsonData = json_decode($cleanJson, true);
        if (!$jsonData) {
            Log::error("JSON invalide retourné par Gemini", [
                'clean_json' => $cleanJson
            ]);
            throw new \Exception('Invalid JSON returned by Gemini');
        }

        Log::info("Extraction terminée", [
            'document_type' => $documentType,
            'keys' => array_keys($jsonData)
        ]);

        return [
            'type_document' => $jsonData['type'] ?? $documentType,
            'supplier_name' => $jsonData['supplier_name'] ?? null,
            'client_name' => $jsonData['client_name'] ?? null,
            'category' => $jsonData['category'] ?? null,
            'invoice_number' => $jsonData['invoice_number'] ?? null,
            'invoice_date' => $jsonData['invoice_date'] ?? null,
            'due_date' => $jsonData['due_date'] ?? null,
            'amount_ht' => $jsonData['amount_ht'] ?? 0,
            'vat_amount' => $jsonData['vat_amount'] ?? 0,
            'total_amount' => $jsonData['total_amount'] ?? 0,
            'currency' => $jsonData['currency'] ?? 'XAF',
            'confidence' => $jsonData['confidence'] ?? 0.85,
            'raw_json' => $jsonData,
            'status' => 'extracted'
        ];
    }

    /**
     * 🔥 Nettoie la réponse Gemini pour extraire uniquement le JSON
     */
    private function extractJson(string $text): string
    {
        preg_match('/\{.*\}/s', $text, $matches);

        if (!isset($matches[0])) {
            throw new \Exception('No JSON found in response');
        }

        return $matches[0];
    }

    private function buildExtractionPrompt($documentType, $companyId)
    {
        return match ($documentType) {
        'invoice' => $this->invoicePrompt(),
            'receipt' => $this->receiptPrompt(),
            default => $this->genericPrompt()
        };
    }

    public function detectMimeType(string $filePath): MimeType
    {
        $filePath = Storage::disk('public')->path($filePath);
        if (!file_exists($filePath)) {
            Log::error("Fichier inexistant: {$filePath}");
            return MimeType::TEXT_PLAIN;
        }

        if (!is_readable($filePath)) {
            Log::error("Fichier non lisible: {$filePath}");
            return MimeType::TEXT_PLAIN;
        }

        // ✅ finfo est toujours disponible (PHP 5.3+)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        Log::info("MIME détecté", [
            'file' => $filePath,
            'mime' => $mimeType,
            'size' => filesize($filePath)
        ]);

        if (!$mimeType) {
            return MimeType::APPLICATION_OCTET_STREAM;
        }

        switch ($mimeType) {
            case 'application/pdf':
            case 'application/x-pdf':
                return MimeType::APPLICATION_PDF;
            case 'image/jpeg':
            case 'image/jpg':
                return MimeType::IMAGE_JPEG;
            case 'image/png':
                return MimeType::IMAGE_PNG;
            case 'image/heic':
                return MimeType::IMAGE_HEIC;
            case 'image/heif':
                return MimeType::IMAGE_HEIF;
            default:
                return MimeType::APPLICATION_OCTET_STREAM;
        }
}
    private function classifyAccount(DocumentExtraction $extraction)
    {
        // Safe: ne casse pas le flow si erreur
        try {
            // TODO IA classification
        } catch (\Throwable $e) {
            Log::warning('Classification failed', [
                'extraction_id' => $extraction->id
            ]);
        }
    }


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
    private function initProjet(Document $document, $userId, $userMessage, $iaMessage)
    {
        $project = Project::create([
            'user_id' => $userId,
            'name' =>'document-'.$document->id,
            'type' => $document->type,
            'status' => 'active',
        ]);

        Message::create([
            'user_id' => $userId,
            'project_id' => $project->id,
            'type' => 'user',
            'text' => $userMessage,
        ]);

        Message::create([
            'user_id' => $userId,
            'project_id' => $project->id,
            'type' => 'ai',
            'text' => $iaMessage,
        ]);
    }
    private function invoicePrompt()
    {
        return <<<PROMPT
Tu es un expert en extraction de données de factures.

Analyse le document fourni et extrait les informations **avec une précision maximale**.

## ⚠️ RÈGLES STRICTES (OBLIGATOIRES)

1. Réponds UNIQUEMENT avec un JSON valide
2. Aucun texte avant ou après le JSON
3. Ne JAMAIS inventer une valeur
4. Si une donnée est absente → mettre null
5. Respecter STRICTEMENT les formats demandés

## 📅 FORMAT DES DONNÉES

- Dates : YYYY-MM-DD
- Montants : nombres (pas de texte, pas de devise)
- Devise : "XAF", "EUR", "USD" uniquement
- confidence : nombre entre 0 et 1

## 🔍 RÈGLES MÉTIER IMPORTANTES

- amount_ht = montant hors taxes
- vat_amount = montant TVA
- total_amount = montant TTC
- total_amount = amount_ht + vat_amount (si possible)

- supplier_name = entreprise qui émet la facture
- client_name = entreprise cliente

## 🧠 CLASSIFICATION

category doit être UNE de ces valeurs :
- telecom (Orange, MTN, Camtel…)
- restaurant (snack, hôtel, food…)
- transport (taxi, carburant, vol…)
- autre

## ❗ GESTION DES CAS DIFFICILES

- Si plusieurs montants → prendre le TOTAL FINAL
- Si TVA absente → vat_amount = 0
- Si devise absente → supposer XAF
- Si doute → réduire confidence

## 📤 FORMAT DE SORTIE

{
  "type": "invoice",
  "supplier_name": string|null,
  "client_name": string|null,
  "category": "telecom|restaurant|transport|autre",
  "invoice_number": string|null,
  "invoice_date": "YYYY-MM-DD"|null,
  "due_date": "YYYY-MM-DD"|null,
  "amount_ht": number,
  "vat_amount": number,
  "total_amount": number,
  "currency": "XAF|EUR|USD",
  "confidence": number
}

PROMPT;
    }

    private function receiptPrompt()
    {

    }

    private function genericPrompt()
    {

    }
}
