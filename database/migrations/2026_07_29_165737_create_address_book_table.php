<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_book', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->string('website')->nullable();
            $table->string('gender');
            $table->unsignedSmallInteger('age');
            $table->string('nationality');
            $table->foreignIdFor(User::class, 'created_by')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('email');
            $table->index('phone');
            $table->index('gender');
            $table->index('nationality');
            $table->index('age');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_book');
    }
};
