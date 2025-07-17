<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_audit_logs', function (Blueprint $table) {
            $table->id();

            // Foreign keys to products and file_uploads
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('file_id');

            // Operation type (insert/update)
            $table->enum('operation', ['insert', 'update']);

            // Who/when/what
            $table->string('changed_ip')->nullable();
            $table->json('changes')->nullable(); // What fields changed, as JSON

            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('file_uploads')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_audit_logs');
    }
};
