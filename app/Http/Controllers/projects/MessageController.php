<?php


namespace App\Http\Controllers\projects;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Project;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    protected $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();

        try {
            // 1. Crée message utilisateur
            $userMessage = Message::create([
                'user_id' => $request->user()->id,
                'project_id' => $project->id,
                'type' => 'user',
                'text' => $request->text,
            ]);

            // 2. Récupère historique propre (15 derniers)
            $history = $project->messages()
                ->orderBy('created_at', 'asc')
                ->limit(15)
                ->get()
                ->map(fn($m) => [
                    'text' => $m->text,
                    'type' => $m->type // 'user' ou 'ai' (string garanti)
                ])
                ->toArray();
            $prompt = $this->buildPrompt($request->text, $project->type);
            // 3. Génère réponse IA (format OFFICIEL Content::parse)
            $aiResponse = $this->gemini->generateResponse($prompt, $history);

            if (!$aiResponse['success']) {
                DB::rollBack();
                Log::warning("IA failed pour projet {$project->id}", [
                    'error' => $aiResponse['error'],
                    'prompt' => substr($request->text, 0, 100)
                ]);

                // Retourne userMessage quand même (UX fluide)
                return response()->json([
                    'success' => false,
                    'user_message' => $userMessage->load('user:id,name'),
                    'ai_error' => config('app.env') === 'production'
                        ? 'IA temporairement indisponible'
                        : $aiResponse['error'],
                ], 207); // Multi-status OK
            }

            // 4. Sauvegarde réponse IA
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
                'ai_message' => $aiMessage->load('user'),
                'usage' => $aiResponse['usage'] ?? null,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("MessageController::store ERROR", [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur serveur',
                'user_message' => isset($userMessage) ? $userMessage->load('user:id,name') : null,
            ], 500);
        }
    }
    // Récupérer messages d'un projet (avec pagination)
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        $messages = $project->messages()
            ->with('user:id,name') // Eager load user minimal
            ->orderBy('created_at', 'asc')
            ->paginate(50); // Pagination pour gros historiques

        return response()->json($messages);
    }
    // Dans un helper, un service ou un contrôleur
    public function buildPrompt(string $msgText, string $projectType): string
    {
        // Tableau de labels par type de projet (à centraliser ailleurs, ex: config/projets.php)
        $projectTypeLabels = [
            'purchase'       => 'Achat fournisseur',
            'sale'           => 'Vente client',
            'expense'        => 'Dépense générale',
            'revenue'        => 'Revenu / recette',
            'tax'            => 'Projet fiscal / impôt',
            'payroll'        => 'Paie / salaires',
            'service'        => 'Service client / support',
            'custom'         => 'Projet personnalisé',
            'internal'       => 'Projet interne',
            'r_d'            => 'Recherche & développement',
            'marketing'      => 'Campagne marketing',
            'event'          => 'Événement / événementiel',
            'training'       => 'Formation / atelier',
            'website'        => 'Site web',
            'app'            => 'Application (mobile / web)',
            'api'            => 'Projet API / intégration',
            'maintenance'    => 'Maintenance / support technique',
            'security'       => 'Sécurité / audit',
            'recruitment'    => 'Recrutement',
            'hr_policy'      => 'Politique RH / réglementation',
            'compliance'     => 'Conformité / audit légal',
            'construction'   => 'Bâtiment / construction',
            'infrastructure' => 'Infrastructure / réseau',
        ];

        $projectLabel = $projectTypeLabels[$projectType] ?? $projectType;

        return "
Tu es un expert spécialisé dans les projets de type : **{$projectLabel}**.
Tu dois répondre à la requête en adaptant ton raisonnement, ton vocabulaire et ton niveau de détail à ce type de projet.

TYPE DE PROJET : {$projectLabel} ({$projectType})

CONTEXTE :
- Si la requête concerne la comptabilité (ex: achat, vente, salaire, etc.) :
  • Utilise le Plan Comptable OHADA Cameroun.
  • Structure les écritures en tableau Excel copiable : DATE | NUMÉRO | LIBELLÉ | COMPTE | DÉBIT | CRÉDIT.
- Si la requête concerne un projet métier ou technique :
  • Propose une structure adaptée : objectifs, étapes, livrables, budget estimé, KPIs, risques.
- Adapte ton discours au domaine (finance, RH, marketing, construction, tech, etc.) selon le type de projet.

REQUÊTE DE L'UTILISATEUR :
{$msgText}
";
    }
}
