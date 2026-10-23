<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config()->string('xendity-ekyc.table', 'xendity_ekyc_transactions'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('kycable');
            $table->string('client_ref_id', 32)->index();
            $table->string('ref_id')->unique();
            $table->string('onboarding_id')->unique();
            $table->string('signature', 100);
            $table->string('document_type', 2);
            $table->string('platform', 10);
            $table->text('onboarding_url');
            $table->timestamp('expired_at')->nullable();
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->unsignedTinyInteger('result')->nullable()->index();
            $table->string('reject_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config()->string('xendity-ekyc.table', 'xendity_ekyc_transactions'));
    }
};
