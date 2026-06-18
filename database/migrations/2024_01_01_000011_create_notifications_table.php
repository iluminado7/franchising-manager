<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->enum('tipo', [
                'nuevo_manual',
                'modificacion_manual',
                'nuevo_documento',
                'manual_asignado',
                'recordatorio_pendiente'
            ]);
            // Tres FK nulables exclusivas — reemplaza el antipatrón polimórfico
            $table->unsignedBigInteger('manual_id')->nullable();
            $table->unsignedBigInteger('manual_version_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('titulo', 200);
            $table->text('mensaje')->nullable();
            $table->tinyInteger('leida')->default(0);
            $table->dateTime('leida_at')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('manual_id')
                  ->references('id')->on('manuals')
                  ->onDelete('cascade');

            $table->foreign('manual_version_id')
                  ->references('id')->on('manual_versions')
                  ->onDelete('cascade');

            $table->foreign('document_id')
                  ->references('id')->on('documents')
                  ->onDelete('cascade');
        });

        // CHECK CONSTRAINT: exactamente una de las tres FK tiene valor
        // Laravel Schema Builder no soporta CHECK directo, se agrega con DB::statement
        DB::statement("
            ALTER TABLE notifications
            ADD CONSTRAINT chk_notification_ref CHECK (
                (manual_id IS NOT NULL AND manual_version_id IS NULL AND document_id IS NULL) OR
                (manual_id IS NULL AND manual_version_id IS NOT NULL AND document_id IS NULL) OR
                (manual_id IS NULL AND manual_version_id IS NULL AND document_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
