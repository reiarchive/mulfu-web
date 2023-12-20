<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTurnitinAvailableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('turnitin_availables', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->text('class_id');
            $table->timestamp('last_success')->nullable();
            $table->boolean('is_used')->default(false);
            $table->string('used_by')->nullable();
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
        Schema::dropIfExists('turnitin_available');
    }
}
