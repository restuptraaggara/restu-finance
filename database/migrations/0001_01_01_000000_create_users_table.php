<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Restu Putra Anggara');
            $table->string('email')->unique()->default('restu@dev.local');
            $table->string('password')->default('$2y$12$e4dZ/R.43sLz1.yJ2u3ZeuJ7x/4.2h9K02Z42d13.');
            $table->string('theme', 20)->default('dark');
            $table->string('currency', 10)->default('IDR');
            $table->string('language', 10)->default('id');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
