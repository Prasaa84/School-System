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
        Schema::create('role_feature_permission_tbl', function (Blueprint $table): void {
            $table->id('role_feature_permission_id');
            $table->unsignedInteger('role_id')->index();
            $table->string('census_id', 7)->index();
            $table->string('feature_key', 80);
            $table->boolean('can_access')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'census_id', 'feature_key'], 'rfp_role_school_feature_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_feature_permission_tbl');
    }
};
