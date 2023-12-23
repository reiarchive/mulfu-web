<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokuCallback extends Model
{
    use HasFactory;

    protected $fillable = [
        'CUSTOMERPAN', 'TRANSACTIONID', 'TXNDATE', 'TERMINALID', 'ISSUERID',
        'ISSUERNAME', 'AMOUNT', 'TXNSTATUS', 'WORDS', 'CUSTOMERNAME',
        'ORIGIN', 'CONVENIENCEFEE', 'ACQUIRER', 'MERCHANTPAN', 'INVOICE', 'REFERENCEID',
    ];
    
}
