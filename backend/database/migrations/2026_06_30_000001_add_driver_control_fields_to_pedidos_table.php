<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pedidos', 'salida_reparto_at')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                $table->dateTime('salida_reparto_at')->nullable();
            });
        }

        if (!Schema::hasColumn('pedidos', 'regreso_reparto_at')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                $table->dateTime('regreso_reparto_at')->nullable()->after('salida_reparto_at');
            });
        }

        if (!Schema::hasColumn('pedidos', 'conductor')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                $table->string('conductor')->nullable();
            });
        }

        if (!Schema::hasColumn('pedidos', 'conductor_dni')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                $table->string('conductor_dni', 20)->nullable()->after('conductor');
            });
        }

        if (!Schema::hasColumn('pedidos', 'vehiculo')) {
            Schema::table('pedidos', function (Blueprint $table): void {
                $table->string('vehiculo')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            foreach (['regreso_reparto_at', 'conductor_dni'] as $column) {
                if (Schema::hasColumn('pedidos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
