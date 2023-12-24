<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileData extends Model
{
    use HasFactory;

    protected $fillable = ['file_id', 'real_file_name', 'title', 'first_author', 'second_author'];

    public function UserTransaction()
    {
        return $this->belongsTo(UserTransaction::class, 'file_id');
    }
}
