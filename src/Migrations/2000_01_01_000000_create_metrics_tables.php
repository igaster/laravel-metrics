<?php

use Igaster\LaravelMetrics\Services\Metrics\Segments\SegmentLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMetricsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug')->unique()->index();
            $table->string('levels');
            $table->string('partitions');
            $table->dateTime('last_sample')->nullable();
        });

        Schema::create('metric_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('metric_id')->default('');
            $table->string('partition_key')->default('');
            $table->dateTime('from');
            $table->dateTime('until');
            $table->integer('count')->default(0);
            $table->double('value')->default(0);
            $table->unsignedSmallInteger('level')->default(SegmentLevel::DAY);

            $table->foreign('metric_id')
                ->on('metrics')
                ->references('id')
                ->onDelete('CASCADE');

            $table->unique(['metric_id', 'level', 'partition_key', 'from']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('metrics');
        Schema::dropIfExists('metric_values');
    }
}
