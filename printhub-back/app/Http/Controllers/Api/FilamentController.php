<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Filaments\StoreFilamentRequest;
use App\Http\Requests\Filaments\UpdateFilamentRequest;
use App\Models\Filament;
use Illuminate\Http\Request;

class FilamentController extends Controller
{
    // #index
    public function index(Request $request)
    {
        // Traz apenas os filamentos do usuário logado
        $filaments = Filament::where('user_id', $request->user()->id)->get();
        return response()->json($filaments, 200);
    }

    // #store
    public function store(StoreFilamentRequest $request)
    {
        $filament = Filament::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);
        
        return response()->json($filament, 201);
    }

    // #show
    public function show(Request $request, string $id)
    {
        // Garante que o filamento pertence ao usuário logado
        $filament = Filament::where('user_id', $request->user()->id)->findOrFail($id);
        
        return response()->json($filament);
    }

    // #update
    public function update(UpdateFilamentRequest $request, string $id)
    {
        // Garante que o usuário só pode editar o próprio filamento
        $filament = Filament::where('user_id', $request->user()->id)->findOrFail($id);

        $filament->update($request->validated());

        return response()->json($filament, 200);
    }

    // #destroy
    public function destroy(Request $request, string $id)
    {
        // Garante que o usuário só pode deletar o próprio filamento
        $filament = Filament::where('user_id', $request->user()->id)->findOrFail($id);

        if ($filament->printJobs()->exists()) {
            return response()->json([
                'message' => 'Nao e possivel excluir um filamento vinculado a jobs.'
            ], 409);
        }

        $filament->delete();

        return response()->json(null, 204);
    }
}
