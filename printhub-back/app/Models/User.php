<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Atributos que devem ser escondidos no retorno da API (JSON).
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversão de tipos de atributos.
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Relacionamento: Um usuário possui muitos filamentos.
     */
    public function filaments()
    {
        return $this->hasMany(Filament::class);
    }

    /**
     * Relacionamento: Um usuário possui muitos jobs de impressão.
     */
    public function jobs()
    {
        return $this->hasMany(PrintJob::class);
    }

    public function setting()
    {
        return $this->hasOne(UserSetting::class);
    }
}
