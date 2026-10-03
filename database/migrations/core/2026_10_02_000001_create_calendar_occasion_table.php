<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendarOccasion', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 160);
            $table->string('occasionCategory', 24);
            $table->string('territorialScope', 32);
            $table->unsignedBigInteger('idCountry');
            $table->unsignedBigInteger('idBrazilState')->nullable();
            $table->unsignedBigInteger('idBrazilMunicipality')->nullable();
            $table->date('validFrom')->nullable();
            $table->date('validTo')->nullable();
            $table->string('recurrenceType', 32);
            $table->date('oneTimeDate')->nullable();
            $table->unsignedTinyInteger('fixedMonth')->nullable();
            $table->unsignedTinyInteger('fixedDay')->nullable();
            $table->unsignedTinyInteger('weekMonth')->nullable();
            $table->string('weekOrdinal', 12)->nullable();
            $table->string('weekDay', 12)->nullable();
            $table->smallInteger('easterOffsetDays')->nullable();
            $table->unsignedBigInteger('idReplacesOptionalDayOff')->nullable();
            $table->dateTime('createdAt', 6)->useCurrent();
            $table->dateTime('updatedAt', 6)->useCurrent()->useCurrentOnUpdate();
            $table->index(['idCountry', 'territorialScope', 'idBrazilState', 'idBrazilMunicipality'], 'idx_calendar_occasion_destination');
            $table->index(['occasionCategory', 'territorialScope'], 'idx_calendar_occasion_category_scope');
            $table->index(['validFrom', 'validTo'], 'idx_calendar_occasion_validity');
            $table->index('recurrenceType', 'idx_calendar_occasion_recurrence');
            $table->index('idReplacesOptionalDayOff', 'idx_calendar_occasion_replaces_optional');
            $table->foreign('idCountry', 'fk_calendar_occasion_country')->references('id')->on('country')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idBrazilState', 'fk_calendar_occasion_brazil_state')->references('id')->on('brazilState')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idBrazilMunicipality', 'fk_calendar_occasion_brazil_municipality')->references('id')->on('brazilMunicipality')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('idReplacesOptionalDayOff', 'fk_calendar_occasion_replaces_optional')->references('id')->on('calendarOccasion')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendarOccasion');
    }
};
