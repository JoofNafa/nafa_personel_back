<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    // Champs autorisés à la création / mise à jour
    protected $fillable = [
        'name',
        'description',

    ];

    /**
     * Un département peut avoir plusieurs employés
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }


}
