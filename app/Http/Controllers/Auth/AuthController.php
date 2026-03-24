<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     * @param Request $request
     * @return JsonResponse
     */

    public function register(Request $request)
    {
        try {

            Log::info('Register request', $request->all());

            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);
            $role=Role::where('name','owner')->first();

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id'=>$role->id
            ]);

            event(new Registered($user)); // envoie email verification

            return response()->json([
                'success' => true,
                'message' => 'Compte créé. Vérifiez votre email.'
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Register validator: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            Log::error('Register error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l’inscription'
            ], 500);
        }
    }

    /**
     * Login user and return token
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'invalid_credentials'
            ], 422);
        }

        // 🔒 Vérifier si l'email est confirmé
        if (!$user->email_verified_at) {
            return response()->json([
                'message' => 'email_not_verified'
            ], 403);
        }

        // 🔑 Vérifier si l'utilisateur est owner et n'a pas d'entreprise
        if ($user->role?->name === 'owner') {
        $hasCompany = $user->companyUser()->exists();
        if (!$hasCompany) {
            logger('non compagmie');
            return response()->json([
                'message' => 'company_missing'
            ], 403);
        }
    }

    // Création du token
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role?->name ?? 'customer',
        ],
        'token'      => $token,
        'token_type' => 'Bearer',
    ], 200);
}

    /**
     * Logout user (Revoke current token)
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * Get authenticated user
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
    // 🔁 Renvoyer le mail de vérification
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email déjà vérifié.'
            ], 400);
        }

        // Générer token temporaire
        $token = Str::random(64);
        $user->email_verification_token = $token;
        $user->save();

        // Envoyer le mail de vérification
       // Mail::to($user->email)->send(new VerifyEmailMail($user));
        $user->sendEmailVerificationNotification();
        return response()->json([
            'message' => 'Lien de vérification renvoyé.'
        ]);
    }
    // ✏️ Modifier l'email et renvoyer le mail de vérification
    public function updateEmail(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'new_email' => 'required|email|unique:users,email',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Utilisateur non trouvé.'
                ], 404);
            }

            $user->update([
                'email' => $request->new_email,
                'email_verified_at' => null,
            ]);

            // Envoi du mail de vérification
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Email mis à jour et lien de vérification renvoyé.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            logger()->error("Erreur updateEmail: ".$e->getMessage());
            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour de l’email.'
            ], 500);
        }
    }
}
