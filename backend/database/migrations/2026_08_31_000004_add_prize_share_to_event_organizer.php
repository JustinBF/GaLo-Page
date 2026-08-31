<?php

use App\Services\CoSplitter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El premio de un evento tambien se reparte entre sus organizadores.
 *
 * Sumando el premio entero a cada uno parecia que todos habian puesto ese
 * dinero, y la columna "Premios repartidos" salia inflada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_organizer', function (Blueprint $table) {
            $table->unsignedBigInteger('prize_share')->default(0)->after('co_share');
        });

        $splitter = new CoSplitter;

        // Mismo reparto que aplica el controlador, para que lo ya cargado
        // quede igual que lo que se guarde a partir de ahora.
        DB::table('events')->orderBy('id')->each(function ($event) use ($splitter) {
            $memberIds = DB::table('event_organizer')
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->pluck('member_id')
                ->all();

            foreach ($splitter->split((int) $event->prize_value, $memberIds) as $memberId => $share) {
                DB::table('event_organizer')
                    ->where('event_id', $event->id)
                    ->where('member_id', $memberId)
                    ->update(['prize_share' => $share]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_organizer', function (Blueprint $table) {
            $table->dropColumn('prize_share');
        });
    }
};
