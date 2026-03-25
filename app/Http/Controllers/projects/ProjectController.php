<?php


namespace App\Http\Controllers\projects;


use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    // Récupérer tous les projets de l'utilisateur connecté
    public function index()
    {
        $projects = Auth::user()->projects()->with('lastMessage')->orderBy('updated_at', 'desc')->get();
        return response()->json($projects);
    }

    // Créer un nouveau projet
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        $project = Auth::user()->projects()->create([
            'name' => $request->name,
            'type' => $request->type,
            'status' => 'active',
        ]);

        return response()->json($project, 201);
    }

    // Détails d'un projet avec messages
    public function show(Project $project)
    {
        $this->authorize('view', $project); // sécurité si user_id != Auth

        $project->load('messages');
        return response()->json($project);
    }

    // Supprimer un projet
    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();
        return response()->json(['message' => 'Projet supprimé avec succès']);
    }
}
