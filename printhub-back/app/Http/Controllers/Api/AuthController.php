<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginAuthRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{

    /**
     * Autentica o usuário e retorna um token.
     */
    public function login(LoginAuthRequest $request)
    {
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciais inválidas.'
            ], Response::HTTP_UNAUTHORIZED); // 401
        }

        $user = $request->user();
        $token = $user->createToken(
            'api-token',
            ['*'],
            now()->addMinutes(config('printhub.token_lifetime_minutes'))
        )->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'token' => $token,
        ]);
    }

    /**
     * Faz logout do usuário (revoga o token atual).
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuário não autenticado.'
            ], Response::HTTP_UNAUTHORIZED);
        }
        $user->currentAccessToken()?->delete();
        return response()->noContent();
    }
}
