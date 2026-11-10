<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Filament extends Model
{   
    use HasFactory;

    protected $fillable = [
        'brand',
        'type',
        'colorName',
        'colorHex',
        'pricePerKg',
        'initialQuantity',
        'currentQuantity',
        'user_id'
    ];

    public function printJobs()
    {
        return $this->belongsToMany(PrintJob::class, 'job_filaments')
                    ->withPivot('weight_grams')
                    ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'pricePerKg' => 'decimal:2',
            'initialQuantity' => 'decimal:3',
            'currentQuantity' => 'decimal:3',
        ];
    }
}
