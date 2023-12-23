<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDokuCallbacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doku_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('CUSTOMERPAN');
            $table->string('TRANSACTIONID');
            $table->string('TXNDATE');
            $table->string('TERMINALID');
            $table->string('ISSUERID');
            $table->string('ISSUERNAME');
            $table->decimal('AMOUNT', 10, 2);
            $table->string('TXNSTATUS');
            $table->string('WORDS');
            $table->string('CUSTOMERNAME');
            $table->string('ORIGIN');
            $table->string('CONVENIENCEFEE');
            $table->string('ACQUIRER');
            $table->string('MERCHANTPAN');
            $table->string('INVOICE');
            $table->string('REFERENCEID');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('doku_callbacks');
    }
}
