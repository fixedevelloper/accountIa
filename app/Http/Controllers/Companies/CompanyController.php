<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{

    /**
     * Liste des companies de l'utilisateur
     */
    public function index(Request $request)
    {
        $companies = $request->user()
            ->companies()
            ->get();

        return response()->json($companies);
    }


    /**
     * Créer une company (onboarding)
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string',
            'currency' => 'required|string',
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.'
            ], 404);
        }

        $company = Company::create([
            'name' => $request->name,
            'country' => $request->country,
            'currency' => $request->currency
        ]);

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner'
        ]);

        DB::commit();
        return response()->json([
            'message' => 'Company créée',
            'company' => $company
        ],201);
    }


    /**
     * Voir une company
     */
    public function show($id)
    {
        $company = Company::findOrFail($id);

        return response()->json($company);
    }


    /**
     * Modifier une company
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'nullable|string',
            'currency' => 'nullable|string'
        ]);

        $company->update($request->all());

        return response()->json([
            'message' => 'Company mise à jour',
            'company' => $company
        ]);
    }


    /**
     * Supprimer une company
     */
    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        $company->delete();

        return response()->json([
            'message' => 'Company supprimée'
        ]);
    }

}
