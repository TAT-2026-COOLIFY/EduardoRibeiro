<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jobs\StoreJobRequest;
use App\Http\Requests\Jobs\UpdateJobRequest;
use App\Models\Filament;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class JobController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            PrintJob::where('user_id', $request->user()->id)->with('filaments')->get()
        );
    }

    public function show(Request $request, string $id)
    {
        $job = PrintJob::where('user_id', $request->user()->id)
            ->with('filaments')
            ->findOrFail($id);

        return response()->json($job);
    }

    public function store(StoreJobRequest $request)
    {
        $storedFile = null;

        try {
            $job = DB::transaction(function () use ($request, &$storedFile) {
                $assignments = $this->assignments($request->validated());
                $filaments = $this->ownedFilaments($request->user()->id, $assignments);
                [$weight, $cost] = $this->metrics($filaments, $assignments, $request->quantity);

                if ($request->status === 'COMPLETED') {
                    $this->decrementStock($filaments, $assignments, $request->quantity);
                }

                $storedFile = $request->file('stl_file')->store('stl_files', 'local');

                $job = PrintJob::create([
                    'fileName' => $request->fileName,
                    'file_path' => $storedFile,
                    'status' => $request->status,
                    'completed_at' => $request->status === 'COMPLETED' ? now() : null,
                    'estimatedTimeMinutes' => $request->estimatedTimeMinutes,
                    'estimatedCost' => $cost,
                    'quantity' => $request->quantity,
                    'materialWeightGrams' => $weight,
                    'layers' => $request->layers,
                    'user_id' => $request->user()->id,
                ]);

                $job->filaments()->attach($assignments);

                return $job;
            });
        } catch (\Throwable $exception) {
            if ($storedFile !== null) {
                $this->deleteFile($storedFile);
            }

            throw $exception;
        }

        return response()->json($job->load('filaments'), 201);
    }

    public function update(UpdateJobRequest $request, string $id)
    {
        $newFile = null;
        $oldFile = null;

        try {
            $job = DB::transaction(function () use ($request, $id, &$newFile, &$oldFile) {
                $job = PrintJob::where('user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->findOrFail($id);
                $data = $request->safe()->except([
                    'stl_file', 'filaments', 'filament_id', 'materialWeightGrams', 'estimatedCost',
                ]);
                $hasAssignments = $request->has('filaments') || $request->has('filament_id');
                $wasCompleted = $job->status === 'COMPLETED';

                if ($wasCompleted && (($request->has('status') && $request->status !== 'COMPLETED') || $hasAssignments || $request->has('quantity'))) {
                    throw ValidationException::withMessages([
                        'status' => 'Um job concluido nao pode ter status ou materiais alterados.',
                    ]);
                }

                $assignments = null;
                $filaments = null;

                if ($hasAssignments) {
                    $assignments = $this->assignments($request->validated());
                    $filaments = $this->ownedFilaments($request->user()->id, $assignments);
                    [$weight, $cost] = $this->metrics($filaments, $assignments, $request->quantity ?? $job->quantity);
                    $data['materialWeightGrams'] = $weight;
                    $data['estimatedCost'] = $cost;
                }

                $isCompleting = !$wasCompleted && ($data['status'] ?? $job->status) === 'COMPLETED';

                if ($isCompleting) {
                    if ($assignments === null) {
                        $assignments = $this->existingAssignments($job);
                        $filaments = $this->ownedFilaments($request->user()->id, $assignments);
                    }

                    $this->decrementStock($filaments, $assignments, $data['quantity'] ?? $job->quantity);
                    $data['completed_at'] = now();
                }

                if ($request->hasFile('stl_file')) {
                    $newFile = $request->file('stl_file')->store('stl_files', 'local');
                    $oldFile = $job->file_path;
                    $data['file_path'] = $newFile;
                }

                if ($assignments !== null && !$wasCompleted) {
                    $job->filaments()->sync($assignments);
                }

                $job->update($data);

                return $job;
            });
        } catch (\Throwable $exception) {
            if ($newFile !== null) {
                $this->deleteFile($newFile);
            }

            throw $exception;
        }

        if ($oldFile !== null) {
            $this->deleteFile($oldFile);
        }

        return response()->json($job->fresh('filaments'));
    }

    public function destroy(Request $request, string $id)
    {
        $filePath = null;

        DB::transaction(function () use ($request, $id, &$filePath) {
            $job = PrintJob::where('user_id', $request->user()->id)
                ->with('filaments')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($job->status === 'COMPLETED') {
                $assignments = $this->existingAssignments($job);
                $filaments = $this->ownedFilaments($request->user()->id, $assignments);
                $this->incrementStock($filaments, $assignments, $job->quantity);
            }

            $filePath = $job->file_path;
            $job->delete();
        });

        if ($filePath !== null) {
            $this->deleteFile($filePath);
        }

        return response()->noContent();
    }

    private function assignments(array $data): array
    {
        if (isset($data['filaments'])) {
            return collect($data['filaments'])
                ->mapWithKeys(fn (array $filament) => [
                    $filament['filament_id'] => ['weight_grams' => $filament['weight_grams']],
                ])
                ->all();
        }

        return [
            $data['filament_id'] => ['weight_grams' => $data['materialWeightGrams']],
        ];
    }

    private function existingAssignments(PrintJob $job): array
    {
        return $job->filaments->mapWithKeys(fn (Filament $filament) => [
            $filament->id => ['weight_grams' => $filament->pivot->weight_grams],
        ])->all();
    }

    private function ownedFilaments(int $userId, array $assignments)
    {
        $filaments = Filament::where('user_id', $userId)
            ->whereIn('id', array_keys($assignments))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($filaments->count() !== count($assignments)) {
            throw ValidationException::withMessages([
                'filaments' => 'Um ou mais filamentos nao pertencem ao usuario autenticado.',
            ]);
        }

        return $filaments;
    }

    private function metrics($filaments, array $assignments, int $quantity): array
    {
        $weightHundredths = 0;
        $costNumerator = 0;

        foreach ($assignments as $id => $pivot) {
            $gramsHundredths = $this->decimalToInteger($pivot['weight_grams'], 2);
            $priceCents = $this->decimalToInteger($filaments->get($id)->pricePerKg, 2);
            $weightHundredths += $gramsHundredths;
            $costNumerator += $priceCents * $gramsHundredths;
        }

        return [
            $this->integerToDecimal($weightHundredths, 2),
            $this->integerToDecimal(intdiv(($costNumerator * $quantity) + 50000, 100000), 2),
        ];
    }

    private function decrementStock($filaments, array $assignments, int $quantity): void
    {
        foreach ($assignments as $id => $pivot) {
            $filament = $filaments->get($id);
            $grams = $this->decimalToInteger($pivot['weight_grams'], 3) * $quantity;

            if ($this->decimalToInteger($filament->currentQuantity, 3) < $grams) {
                throw ValidationException::withMessages([
                    'filaments' => 'Estoque de filamento insuficiente.',
                ]);
            }
        }

        foreach ($assignments as $id => $pivot) {
            $filaments->get($id)->decrement('currentQuantity', $pivot['weight_grams'] * $quantity);
        }
    }

    private function incrementStock($filaments, array $assignments, int $quantity): void
    {
        foreach ($assignments as $id => $pivot) {
            $filaments->get($id)->increment('currentQuantity', $pivot['weight_grams'] * $quantity);
        }
    }

    private function decimalToInteger(string|int $value, int $scale): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);

        return ((int) $whole * (10 ** $scale)) + (int) $fraction;
    }

    private function integerToDecimal(int $value, int $scale): string
    {
        $divisor = 10 ** $scale;

        return intdiv($value, $divisor).'.'.str_pad((string) ($value % $divisor), $scale, '0', STR_PAD_LEFT);
    }

    private function deleteFile(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }
}
