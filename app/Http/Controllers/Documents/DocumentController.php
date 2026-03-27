<?php


namespace App\Http\Controllers\Documents;


use App\Http\Resources\DocumentExtractionResource;
use App\Http\Resources\DocumentResource;
use App\Jobs\AnalyzeDocumentJob;
use App\Jobs\ProcessDocumentExtraction;
use App\Models\Account;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Message;
use App\Models\Partner;
use App\Models\Project;
use App\Models\VatDeclaration;
use App\Services\GoogleGeminiExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class DocumentController
{
    protected $gemini;

    public function __construct(GoogleGeminiExtractorService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function index(Request $request)
    {
        $query = DocumentExtraction::query();

        // 🔎 Filtres utiles
        if ($request->filled('document_id')) {
            $query->where('document_id', $request->document_id);
        }

        if ($request->filled('supplier_name')) {
            $query->where('supplier_name', 'like', '%' . $request->supplier_name . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        $extractions = $query->latest()->paginate(10);

        return DocumentExtractionResource::collection($extractions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:30720',
            'type' => 'sometimes|string'
        ]);

        $user = Auth::user();
        $companyId = $user->companyUser->company_id;

        try {
            // 1. Upload fichier
            $documentPath = $request->file('file')
                ->store("companies/{$companyId}/documents", 'public');

            // 2. Création document
            $document = Document::create([
                'company_id' => $companyId,
                'type' => $validated['type'] ?? 'invoice',
                'file_path' => $documentPath,
                'status' => 'uploaded'
            ]);


            // 3. Dispatch job (queue dédiée)
            /*            ProcessDocumentExtraction::dispatch($document->id,$user->id, $document->type)
                            ->onQueue('extraction');*/
            ProcessDocumentExtraction::dispatch($document->id, $user->id, $document->type);

            return response()->json([
                'success' => true,
                'message' => 'Document en file d\'attente pour extraction AI',
                'document' => $document->load('company'),
                'document_id' => $document->id,
                'status' => 'uploaded'
            ], 202);

        } catch (\Throwable $e) {

            Log::error('Document upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du document'
            ], 500);
        }
    }

    /*    public function store(Request $request)
        {
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
            ]);

            $user = Auth::user();

            $file = $request->file('file');

           // $path = $file->store('documents', 's3');
            $path = $file->store('documents', 'public');
            $document = Document::create([
                'company_id' => $user->companyUser->company_id,
                'file_path' => $path,
                'status' => 'uploaded',
            ]);

            // envoyer vers la queue pour analyse IA
            AnalyzeDocumentJob::dispatch($document);

            return response()->json([
                'success' => true,
                'document_id' => $document->id,
                'status' => 'processing'
            ]);
        }*/
    public function show($id)
    {
        $document = Document::with([
            'company',
            'partner',
            'versions',
            'extractions',
            'aiClassifications',
            'journalEntries.lines.account',
        ])->findOrFail($id);

        return response()->json(new DocumentResource($document));
    }

    public function sendMessageDocument(Request $request, $id)
    {
        $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();

        try {
            $user = Auth::user();
            $name = 'document-' . $id;
            $project = Project::query()->where(['user_id' => $user->id, 'name' => $name])->first();
            $document = Document::find($id);

            // 🔥 DÉTECTION ACTION (1-5) dans le message utilisateur
            $actionNumber = $this->detectActionNumber($request->text);
            $isAction = $actionNumber > 0;

            // 🔥 Message user
            $userMessage = Message::create([
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'type' => 'user',
                'text' => $request->text,
            ]);

            // 🔥 Historique
            $history = $project->messages()
                ->latest()
                ->limit(15)
                ->get()
                ->reverse()
                ->map(fn($m) => [
                    'text' => $m->text,
                    'type' => $m->type
                ])
                ->values()
                ->toArray();

            // 🔥 CONSTRUCTION PROMPT ADAPTÉ
            $prompt = $isAction
                ? $this->buildActionPrompt($actionNumber)
                : $this->buildPromptSingle($request->text);

            // 🔥 IA Response
            $aiResponse = $this->gemini->generateResponse($prompt, $history);

            if (!$aiResponse['success']) {
                DB::rollBack();
                Log::warning("IA failed", [
                    'project_id' => $project->id,
                    'error' => $aiResponse['error']
                ]);
                return response()->json([
                    'success' => false,
                    'user_message' => $userMessage->load('user:id,name'),
                    'ai_error' => config('app.env') === 'production'
                        ? 'IA temporairement indisponible'
                        : $aiResponse['error'],
                ], 207);
            }

            // 🔥 PARSE ACTION JSON si applicable
            $actionData = null;
            $textIa = '';
            if ($isAction) {
                $actionData = $this->parseActionJson($aiResponse['text']);
                if ($isAction && $actionData) {
                    $companyId = $project->company_id ?? $document->company_id;

                    switch ($actionNumber) {
                        case 1: // Enregistrer écritures comptables
                            $this->executeAction1EnregistrerEcritures($companyId, $actionData, $document);
                            $textIa .= "\n\n✅ **Écritures enregistrées** dans le journal " . ($actionData['journal'] ?? 'ACH');
                            break;

                        case 2: // Créer fournisseur
                            $partnerId = $this->executeAction2CreerFournisseur($companyId, $actionData);
                            $document->update(['partner_id' => $partnerId]);
                            $textIa .= "\n\n✅ **Fournisseur créé** (ID: {$partnerId})";
                            break;

                        case 3: // Classer facture
                            $this->executeAction3ClasserFacture($document, $actionData);
                            $textIa .= "\n\n✅ **Facture classée** : " . ucfirst($actionData['statut'] ?? 'unknown');
                            break;

                        case 4: // Déclaration TVA
                            $vatDeclId = $this->executeAction4DeclarationTVA($companyId, $actionData);
                            $textIa .= "\n\n✅ **Déclaration TVA créée** (ID: {$vatDeclId})";
                            break;

                        case 5: // Export compta
                            $exportPath = $this->executeAction5ExporterCompta($actionData);
                            $textIa .= "\n\n✅ **Export généré** : " . basename($exportPath);
                            break;

                        default:
                            Log::warning("Action inconnue", ['action' => $actionNumber]);
                    }

                    //$aiMessage->save();
                }
                Log::info("Action {$actionNumber} détectée", ['data' => $actionData]);
            }

            // 🔥 Message IA
            $aiMessage = Message::create([
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'type' => 'ai',
                'text' => $aiResponse['text'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'user_message' => $userMessage->load('user:id,name'),
                'ai_message' => $aiMessage->load('user:id,name'),
                'action_executed' => $isAction ? $actionNumber : null,
                'action_data' => $actionData,
                'usage' => $aiResponse['usage'] ?? null,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("sendMessageDocument ERROR", [
                'document_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => 'Erreur serveur'], 500);
        }
    }

    /**
     * Détecte si l'utilisateur demande une action (1️⃣, "action 1", etc.)
     */
    private function detectActionNumber(string $text): ?int
    {
        preg_match('/(?:action\s*)?(\d)/i', $text, $matches);
        $number = (int)($matches[1] ?? 0);
        return $number >= 1 && $number <= 5 ? $number : null;
    }

    private function buildActionPrompt(int $actionNumber): string
    {
        $prompts = [
            1 => $this->promptEnregistrerEcritures(),
            2 => $this->promptCreerFournisseur(),
            3 => $this->promptClasserFacture(),
            4 => $this->promptDeclarationTVA(),
            5 => $this->promptExporterCompta()
        ];
        return $prompts[$actionNumber] ?? $this->buildPromptSingle('');
    }

// Corrigez buildActionPrompt pour accepter documentData

    private function promptEnregistrerEcritures(): string
    {
        return <<<PROMPT
Tu es un expert comptable OHADA. ACTION 1 : ENREGISTRER LES ÉCRITURES.


**JSON FINAL pour DB :**
{
  "journal": "ACH", "date": "YYYY-MM-DD", "ecritures": [...],
  "statut": "ENREGISTRE"
}
Réponds UNIQUEMENT JSON valide.
PROMPT;
    }

// Ajoutez $documentData aux prompts d'action

    private function promptCreerFournisseur(): string
    {
        return <<<PROMPT
Tu es un expert comptable OHADA. ACTION 2 : CRÉER LE FOURNISSEUR.


**GÉNÈRE le JSON pour créer le fournisseur :**

{
  "nom": "NOM FOURNISSEUR",
  "siret": "123456789",
  "tva": "FRXX123456789",
  "adresse": "Douala Cameroun",
  "telephone": "+237XXXXXXXXX",
  "compte_tiers": "401000",
  "statut": "ACTIF"
}

Réponds UNIQUEMENT avec ce JSON valide.
PROMPT;
    }

    private function promptClasserFacture(): string
    {
        return <<<PROMPT
ACTION 3 : CLASSER LA FACTURE.

**Analyse et classe :**
{
  "statut": "PAYEE|PARTIELLE|IMPAYEE",
  "montant_paye": 480.00,
  "date_paiement": "2021-07-20",
  "mode_paiement": "MoMo|Especes|Cheque",
  "commentaire": "Payé via MTN MoMo"
}
PROMPT;
    }

    private function promptDeclarationTVA(): string
    {
        return <<<PROMPT
ACTION 4 : DÉCLARATION TVA.


**JSON déclaration TVA Cameroun :**
{
  "periode": "Juillet 2021",
  "tva_collectee": 0,
  "tva_deductible": 80.00,
  "tva_nette": -80.00,
  "factures": [{"num": "552", "tva": 80.00}]
}
PROMPT;
    }

    private function promptExporterCompta(): string
    {
        return <<<PROMPT
ACTION 5 : EXPORT CIEL/SAGE.

**Format export compatible Ciel Compta :**
J. ACH 12072021 552
607100 Achat 400.00
445620 TVA 80.00
401000 Fournisseur -480.00OU JSON Sage :
{
  "format": "CIEL|SAGE|UBS",
  "contenu": "..."
}
PROMPT;
    }

    /**
     * Parse JSON d'action depuis réponse IA
     */
    private function parseActionJson(string $response): ?array
    {
        preg_match('/\{.*\}/s', $response, $matches);
        if (!empty($matches[0])) {
            $json = $matches[0];
            $data = json_decode($json, true);
            return json_last_error() === JSON_ERROR_NONE ? $data : null;
        }
        return null;
    }

    private function executeAction1EnregistrerEcritures(int $companyId, array $data, Document $document)
    {
        DB::transaction(function () use ($companyId, $data, $document) {
            // 1. Trouver/Créer journal
            $journal = Journal::firstOrCreate(
                ['company_id' => $companyId, 'code' => $data['journal'] ?? 'ACH'],
                ['name' => 'Achats', 'type' => 'purchase']
            );

            // 2. Créer écriture
            $entry = JournalEntry::create([
                'document_id' => $document->id,
                'company_id' => $companyId,
                'journal_id' => $journal->id,
                'reference' => $document->extracted_data['invoice_number'] ?? 'AI-GEN',
                'entry_date' => $data['date'] ?? now(),
                'status' => 'confirmed'
            ]);

            // 3. Lignes d'écriture
            foreach ($data['ecritures'] ?? [] as $line) {
                $account = Account::firstOrCreate(
                    ['company_id' => $companyId, 'code' => $line['compte']],
                    ['name' => $line['libelle'] ?? 'Compte générique', 'type' => 'expense']
                );

                JournalEntryLine::create([
                    'entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['libelle'] ?? ''
                ]);
            }

            Log::info("Écritures enregistrées", [
                'entry_id' => $entry->id,
                'document_id' => $document->id
            ]);
        });
    }

    private function executeAction2CreerFournisseur(int $companyId, array $data)
    {
        return DB::transaction(function () use ($companyId, $data) {
            $partner = Partner::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $data['nom'] ?? $data['supplier_name'] ?? 'Fournisseur AI'
                ],
                [
                    'type' => 'supplier',
                    'tax_number' => $data['siret'] ?? $data['tva'] ?? null
                ]
            );

            Log::info("Fournisseur créé/modifié", ['partner_id' => $partner->id]);
            return $partner->id;
        });
    }

    private function executeAction3ClasserFacture(Document $document, array $data)
    {
        DB::transaction(function () use ($document, $data) {
            $document->update([
                'status' => $data['statut'] ?? 'paid',
                'payment_status' => $data['statut'] ?? 'paid'
            ]);

            // Mettre à jour extraction
            $extraction = $document->extraction;
            $extraction->update([
                'payment_status' => $data['statut'] ?? 'paid'
            ]);
        });
    }

    private function executeAction4DeclarationTVA(int $companyId, array $data)
    {
        return DB::transaction(function () use ($companyId, $data) {
            return VatDeclaration::create([
                'company_id' => $companyId,
                'period_start' => $data['periode_start'] ?? now()->startOfMonth(),
                'period_end' => $data['periode_end'] ?? now()->endOfMonth(),
                'vat_collected' => $data['tva_collectee'] ?? 0,
                'vat_deductible' => $data['tva_deductible'] ?? 0,
                'vat_payable' => $data['tva_nette'] ?? 0
            ])->id;
        });
    }

    private function executeAction5ExporterCompta(array $data)
    {
        $content = "Journal: " . ($data['journal'] ?? 'ACH') . "\n";
        foreach ($data['ecritures'] ?? [] as $line) {
            $content .= sprintf("%s %s %.2f\n",
                $line['compte'],
                substr($line['libelle'], 0, 30),
                $line['debit'] - $line['credit']
            );
        }

        $path = storage_path("app/exports/ecriture-" . now()->format('YmdHis') . ".txt");
        file_put_contents($path, $content);

        return $path;
    }

    private function formatDocumentData(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
    }

}
