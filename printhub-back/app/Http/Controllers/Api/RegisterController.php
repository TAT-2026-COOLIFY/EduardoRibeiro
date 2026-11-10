<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Register\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request){

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken(
            'PrintHub_token',
            ['*'],
            now()->addMinutes(config('printhub.token_lifetime_minutes'))
        )->plainTextToken;

        return response()->json([
            'message' => 'Registro realizado com sucesso.',
            'token' => $token,
            'data' => $user,
        ], Response::HTTP_CREATED);
    }
}
