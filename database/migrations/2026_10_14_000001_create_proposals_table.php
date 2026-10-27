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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->integer('serial_no')->nullable()->comment('S.No for sorting & display');
            $table->date('date');
            $table->string('customer', 255);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('proposal_reference_no', 100);
            $table->text('drive_link');
            $table->enum('status', ['Preparing', 'Pending', 'Sent', 'Approved', 'Reject'])->default('Preparing');
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['serial_no']);
            $table->index(['proposal_reference_no']);
            $table->index(['status']);
            $table->index(['date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
