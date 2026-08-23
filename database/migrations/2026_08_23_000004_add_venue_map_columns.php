<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 会場図（イベント全体の見取り図）と、その上でのサークルの位置。
 *
 * event_circles の map_x / map_y は「そのサークル用に切り取った画像」の中の位置なので、
 * 会場図上の位置は別の列として持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('map_image_path')->nullable()->after('image_path');
        });

        Schema::table('event_circles', function (Blueprint $table) {
            $table->unsignedTinyInteger('venue_map_x')->nullable()->after('map_y');
            $table->unsignedTinyInteger('venue_map_y')->nullable()->after('venue_map_x');
        });
    }

    public function down(): void
    {
        Schema::table('event_circles', function (Blueprint $table) {
            $table->dropColumn(['venue_map_x', 'venue_map_y']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('map_image_path');
        });
    }
};
