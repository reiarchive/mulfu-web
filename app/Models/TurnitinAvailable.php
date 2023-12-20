<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TurnitinAvailable extends Model
{
    use HasFactory;

    protected $fillable = ['is_used', 'used_by'];

}
