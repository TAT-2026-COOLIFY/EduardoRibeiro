<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return response()->json([$request->user()]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request-> validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $validated['password'] = bcrypt($validated['password']);

        $user = User::create($validated);

        return response()-> json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = User::whereKey($request->user()->id)->findOrFail($id);

        return response()-> json($user);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::whereKey($request->user()->id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'sometimes|string|min:8',
            ]);

            if(isset($validated['password'])){
                $validated['password'] = bcrypt($validated['password']);
            }

            $user->update($validated);

            return response()-> json($user, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = User::whereKey($request->user()->id)->findOrFail($id);
        $filePaths = [];

        DB::transaction(function () use ($user, &$filePaths) {
            $filePaths = $user->jobs()->pluck('file_path')->all();
            $user->tokens()->delete();
            $user->delete();
        });

        foreach ($filePaths as $filePath) {
            Storage::disk('local')->delete($filePath);
            Storage::disk('public')->delete($filePath);
        }

        return response()-> json(null, 204);
    }
}
