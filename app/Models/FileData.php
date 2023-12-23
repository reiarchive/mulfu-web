<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileData extends Model
{
    use HasFactory;

    protected $fillable = ['file_id', 'real_file_name'];

    public function UserTransaction()
    {
        return $this->belongsTo(UserTransaction::class, 'file_id');
    }
}
