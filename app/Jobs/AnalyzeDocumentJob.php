<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AnalyzeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;

    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    public function handle()
    {
        $document = $this->document;

        $document->update(['status' => 'processing']);

        $documentUrl = asset('storage/' . $document->file_path);
        logger("📄 OCR START: " . $documentUrl);

        try {

            $response = Http::timeout(120)
                ->asForm()
                ->post(env('PYTHON_OCR_URL') . '/analyze-document', [
                    'id_document' => $document->id,
                    'document_url' => $documentUrl,
                ]);

            if (!$response->successful()) {
                logger("❌ OCR API failed: " . $response->body());
                return $this->failDocument($document);
            }

            $data = $response->json();

            if (empty($data['documents'][0])) {
                logger("❌ OCR empty response");
                return $this->failDocument($document);
            }

            $parsed = $this->parseOcrResponse($data['documents'][0]);

            if (!$parsed) {
                logger("❌ OCR parsing failed");
                return $this->failDocument($document);
            }

            logger("✅ OCR parsed", $parsed);

            // 🔥 NORMALISATION FINANCIÈRE
            $normalized = $this->normalizeData($parsed);

            // 🔥 SAVE
            $document->extractions()->create($normalized);

            $document->update(['status' => 'processed']);

            // 🔥 Comptabilité
            app(AccountingService::class)->createEntryFromDocument($document);
            $document->update(['status' => 'success']);
            logger("✅ DONE document: " . $document->id);

        } catch (\Exception $e) {
            logger("❌ OCR ERROR: " . $e->getMessage());
            $this->failDocument($document);
        }
    }

    private function failDocument($document)
    {
        $document->update(['status' => 'failed']);
    }

    /**
     * 🔥 Parser OCR multi-format
     * @param $extraction
     * @return mixed|null
     */
    private function parseOcrResponse($extraction)
    {
        // Cas 1 : JSON direct
        if (isset($extraction['supplier_name']) || isset($extraction['nom'])) {
            return $extraction;
        }

        // Cas 2 : raw_response
        if (!isset($extraction['raw_response'])) {
            return null;
        }

        $raw = $extraction['raw_response'];

        $clean = str_replace(['```json', '```'], '', $raw);

        $parsed = json_decode(trim($clean), true);

        if (!$parsed) {
            logger("❌ JSON invalide: " . $clean);
            return null;
        }

        return $parsed;
    }

    /**
     * 🔥 Normalisation complète
     */
    private function normalizeData($data)
    {
        $rawAmount = $data['total_amount'] ?? $data['montant'] ?? null;

        $total = $this->parseAmount($rawAmount);
        $ht    = $this->parseAmount($data['amount_ht'] ?? null);
        $vat   = $this->parseAmount($data['vat_amount'] ?? null);

        // 🔥 Auto-calcul TVA (Cameroun 19.25%)
        if ($total && !$ht && !$vat) {
            $vat = round($total * 0.1925, 2);
            $ht  = round($total - $vat, 2);
        }

        return [
            // 📄 Infos
            'type_document' => $data['type_document'] ?? null,
            'category'      => $data['category'] ?? null,

            'supplier_name' => $data['supplier_name'] ?? $data['nom'] ?? null,
            'client_name'   => $data['client_name'] ?? null,

            // 🔢 Facture
            'invoice_number' => $data['invoice_number'] ?? $data['reference'] ?? null,
            'invoice_date'   => $this->parseDate($data['invoice_date'] ?? $data['date'] ?? null),
            'due_date'       => $this->parseDate($data['due_date'] ?? null),

            'payment_status' => $data['payment_status'] ?? 'unpaid',

            // 💰 Montants
            'amount_ht'    => $ht,
            'vat_amount'   => $vat,
            'total_amount' => $total,

            // 🌍 Devise
            'currency' => $data['currency'] ?? $this->extractCurrency($rawAmount) ?? 'XAF',

            // 🤖 OCR
            'confidence' => $data['confidence'] ?? null,
            'status'     => 'extracted',

            // 🧾 brut
            'raw_json' => $data,

            'is_validated' => false,
        ];
    }

    /**
     * 🔥 Parser montant
     */
    private function parseAmount($amount)
    {
        if (!$amount) return null;

        $clean = preg_replace('/[^0-9,.\-]/', '', $amount);
        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * 🔥 Parser devise
     */
    private function extractCurrency($amount)
    {
        if (!$amount) return null;

        if (str_contains($amount, '€')) return 'EUR';
        if (str_contains($amount, '$')) return 'USD';
        if (str_contains($amount, 'FCFA') || str_contains($amount, 'XAF')) return 'XAF';

        return null;
    }

    /**
     * 🔥 Parser date FR
     */
    private function parseDate($date)
    {
        if (!$date) return null;

        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {

            $months = [
                'Janvier' => 'January',
                'Février' => 'February',
                'Mars' => 'March',
                'Avril' => 'April',
                'Mai' => 'May',
                'Juin' => 'June',
                'Juillet' => 'July',
                'Août' => 'August',
                'Septembre' => 'September',
                'Octobre' => 'October',
                'Novembre' => 'November',
                'Décembre' => 'December',
            ];

            $date = str_replace(array_keys($months), array_values($months), $date);

            try {
                return \Carbon\Carbon::parse($date);
            } catch (\Exception $e) {
                logger("❌ Date invalide: " . $date);
                return null;
            }
        }
    }
}
