<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Filament;

class PrintJob extends Model
{
    protected $fillable = [
        'user_id',
        'fileName',
        'file_path',
        'status',
        'estimatedTimeMinutes',
        'estimatedCost',
        'quantity',
        'materialWeightGrams',
        'layers',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'estimatedCost' => 'decimal:2',
            'materialWeightGrams' => 'decimal:2',
        ];
    }

    public function filaments()
    {
        return $this->belongsToMany(Filament::class, 'job_filaments')
                    ->withPivot('weight_grams')
                    ->withTimestamps();
    }
}
