<?php

use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\projects\MessageController;
use App\Http\Controllers\projects\ProjectController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\URL;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/auth/update-email', [AuthController::class, 'updateEmail']);
Route::post('/auth/verify-email', function (Request $request) {
    $request->validate([
        'id'   => 'required|integer',
        'hash' => 'required|string',
    ]);

    try {
        $user = User::findOrFail($request->id);

        // Vérifier que le hash correspond à l'email de l'utilisateur
        $calculatedHash = sha1($user->getEmailForVerification());
        if (! hash_equals($calculatedHash, $request->hash)) {
            return response()->json([
                'message' => 'Lien de vérification invalide.'
            ], 403);
        }

        // Vérifier si l'utilisateur est déjà vérifié
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email déjà vérifié.'
            ]);
        }

        // Marquer l'email comme vérifié
        $user->markEmailAsVerified();

        return response()->json([
            'message' => 'Email vérifié avec succès.',
            'email_verified_at' => $user->email_verified_at
        ]);

    } catch (\Exception $e) {
        Log::error('Erreur vérification email: '.$e->getMessage());
        return response()->json([
            'message' => 'Impossible de vérifier l’email.',
        ], 500);
    }
});
Route::apiResource('companies', CompanyController::class);
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {

    $user = User::findOrFail($id);

    // vérifier signature du lien
    if (! URL::hasValidSignature($request)) {
        return redirect(env('FRONTEND_URL').'/auth/email-error');
    }

    // vérifier hash email
    if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
        return redirect(env('FRONTEND_URL').'/auth/email-error');
    }

    // vérifier si déjà vérifié
    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return redirect(env('FRONTEND_URL').'/');

})->name('verification.verify');
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::apiResource('documents', DocumentController::class);
    Route::middleware('auth:sanctum')->group(function () {

        // Projets
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

        // Messages
        Route::get('/projects/{project}/messages', [MessageController::class, 'index']);
        Route::post('/projects/{project}/messages', [MessageController::class, 'store']);
    });
});

