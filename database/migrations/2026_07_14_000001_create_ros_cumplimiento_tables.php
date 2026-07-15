<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ros_casos', function (Blueprint $table) {
            $table->bigIncrements('RosCasoID');
            $table->unsignedBigInteger('SedeID')->index();
            $table->unsignedBigInteger('ZonaID')->nullable()->index();
            $table->unsignedBigInteger('ClienteID')->nullable()->index();
            $table->unsignedBigInteger('CreditoID')->nullable()->index();
            $table->unsignedBigInteger('PagoID')->nullable()->index();
            $table->string('CodigoInterno', 40)->unique();
            $table->string('Estado', 30)->default('BORRADOR')->index();
            $table->string('ClaseReporte', 20)->default('INICIAL');
            $table->string('NumeroReporteAnterior', 60)->nullable();
            $table->date('FechaReporteAnterior')->nullable();
            $table->date('FechaDeteccion')->nullable()->index();
            $table->date('FechaOperacionDesde')->nullable();
            $table->date('FechaOperacionHasta')->nullable();
            $table->decimal('MontoTotal', 14, 2)->nullable();
            $table->string('Moneda', 10)->default('PEN');
            $table->string('DelitoPrecedente', 150)->nullable();
            $table->string('Alcance', 20)->default('NACIONAL');
            $table->text('PaisesRelacionados')->nullable();
            $table->string('SectorEconomico', 150)->nullable();
            $table->string('ActividadEconomica', 150)->nullable();
            $table->longText('DescripcionHechos')->nullable();
            $table->longText('ConclusionEvaluacion')->nullable();
            $table->timestamp('FechaReportado')->nullable();
            $table->unsignedBigInteger('CreadoPorID')->nullable()->index();
            $table->unsignedBigInteger('ActualizadoPorID')->nullable()->index();
            $table->boolean('EsDatosPrueba')->default(false)->index();
            $table->timestamps();
            $table->index(['SedeID', 'Estado', 'FechaDeteccion'], 'idx_ros_caso_sede_estado_fecha');
        });

        Schema::create('ros_personas', function (Blueprint $table) {
            $table->bigIncrements('RosPersonaID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->unsignedBigInteger('ClienteID')->nullable()->index();
            $table->string('TipoPersona', 20);
            $table->string('CondicionParticipacion', 80)->nullable();
            $table->string('ApellidoPaterno', 100)->nullable();
            $table->string('ApellidoMaterno', 100)->nullable();
            $table->string('Nombres', 150)->nullable();
            $table->string('RazonSocial', 200)->nullable();
            $table->string('TipoDocumento', 50)->nullable();
            $table->string('NumeroDocumento', 50)->nullable()->index();
            $table->date('FechaNacimiento')->nullable();
            $table->string('Nacionalidad', 80)->nullable();
            $table->boolean('EsPep')->default(false);
            $table->string('ProfesionOcupacion', 150)->nullable();
            $table->string('ActividadEconomica', 150)->nullable();
            $table->string('Domicilio', 300)->nullable();
            $table->string('Telefono', 80)->nullable();
            $table->string('Correo', 150)->nullable();
            $table->decimal('IngresoMensual', 14, 2)->nullable();
            $table->string('MonedaIngreso', 10)->nullable();
            $table->timestamps();
            $table->index(['RosCasoID', 'TipoPersona'], 'idx_ros_persona_caso_tipo');
        });

        Schema::create('ros_operaciones', function (Blueprint $table) {
            $table->bigIncrements('RosOperacionID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->unsignedBigInteger('ClienteID')->nullable()->index();
            $table->unsignedBigInteger('CreditoID')->nullable()->index();
            $table->unsignedBigInteger('PagoID')->nullable()->index();
            $table->string('ProductoServicio', 150);
            $table->string('CodigoProducto', 20)->nullable();
            $table->string('NumeroOperacion', 100)->nullable();
            $table->decimal('Monto', 14, 2)->nullable();
            $table->string('Moneda', 10)->default('PEN');
            $table->date('FechaOperacion')->nullable()->index();
            $table->text('Detalle')->nullable();
            $table->timestamps();
            $table->index(['RosCasoID', 'FechaOperacion'], 'idx_ros_operacion_caso_fecha');
        });

        Schema::create('ros_senales_alerta', function (Blueprint $table) {
            $table->bigIncrements('RosSenalAlertaID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->string('Tipo', 30);
            $table->string('Codigo', 30)->nullable();
            $table->text('Descripcion');
            $table->timestamps();
            $table->index(['RosCasoID', 'Tipo'], 'idx_ros_senal_caso_tipo');
        });

        Schema::create('ros_tipologias', function (Blueprint $table) {
            $table->bigIncrements('RosTipologiaID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->string('Codigo', 30)->nullable();
            $table->text('Descripcion');
            $table->timestamps();
        });

        Schema::create('ros_adjuntos', function (Blueprint $table) {
            $table->bigIncrements('RosAdjuntoID');
            $table->unsignedBigInteger('RosCasoID')->index();
            $table->unsignedBigInteger('SedeID')->index();
            $table->string('RutaArchivo', 500);
            $table->string('NombreOriginal', 255)->nullable();
            $table->string('TipoMime', 120)->nullable();
            $table->unsignedBigInteger('TamanioBytes')->nullable();
            $table->string('Descripcion', 255)->nullable();
            $table->unsignedBigInteger('SubidoPorID')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ros_adjuntos');
        Schema::dropIfExists('ros_tipologias');
        Schema::dropIfExists('ros_senales_alerta');
        Schema::dropIfExists('ros_operaciones');
        Schema::dropIfExists('ros_personas');
        Schema::dropIfExists('ros_casos');
    }
};
