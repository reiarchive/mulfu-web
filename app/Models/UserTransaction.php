<?php

namespace App\Models;

use App\Models\FileData;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['status'];

    public static function generateRandomId()
    {
        $segments = [
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
        ];

        return implode('-', $segments);
    }

    public function fileData()
    {
        return $this->hasOne(FileData::class, 'file_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
