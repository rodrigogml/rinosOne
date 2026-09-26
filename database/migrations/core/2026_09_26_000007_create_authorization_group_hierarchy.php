<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_group_group', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('idParentGroup');
            $table->unsignedBigInteger('idChildGroup');
            $table->primary(['idParentGroup', 'idChildGroup'], 'pk_auth_group_group');
            $table->index('idChildGroup', 'idx_auth_group_group_child');
            $table->foreign('idParentGroup', 'fk_auth_group_group_parent')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idChildGroup', 'fk_auth_group_group_child')->references('id')->on('auth_group')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_group_group');
    }
};
