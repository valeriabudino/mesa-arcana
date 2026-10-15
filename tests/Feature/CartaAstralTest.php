<?php

use App\Enums\EstadoLectura;
use App\Enums\Signo;
use App\Enums\TipoLectura;
use App\Events\CartaAstralCalculada;
use App\Events\DatosNacimientoRecibidos;
use App\Events\PlanetaSenalado;
use App\Models\Ciudad;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\User;
use App\Services\CartaAstralService;
use Database\Seeders\CiudadSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

/** Corre el script de verdad (sin Laravel en el medio) con un instante UTC y coordenadas. */
function calcularAstro(string $utc, float $lat, float $lng, bool $horaConocida = true): array
{
    $proceso = Process::path(base_path())->run(['node', 'resources/astro/calcular.mjs', $utc, (string) $lat, (string) $lng, $horaConocida ? '1' : '0']);
    expect($proceso->successful())->toBeTrue();

    return json_decode($proceso->output(), true);
}

describe('el cálculo, contra cartas y eventos conocidos', function () {
    it('Einstein (14/3/1879, 11:30 hora local de Ulm): Sol 23°30\' Piscis, Luna 14°31\' Sagitario, Asc 11°38\' Cáncer', function () {
        $carta = calcularAstro('1879-03-14T10:50:00Z', 48.4011, 9.9876);

        expect(Signo::deLongitud($carta['sol']))->toBe(Signo::Piscis)
            ->and($carta['sol'])->toEqualWithDelta(330 + 23.5, 0.5)
            ->and(Signo::deLongitud($carta['luna']))->toBe(Signo::Sagitario)
            ->and($carta['luna'])->toEqualWithDelta(240 + 14.52, 0.5)
            ->and(Signo::deLongitud($carta['ascendente']))->toBe(Signo::Cancer)
            ->and($carta['ascendente'])->toEqualWithDelta(90 + 11.63, 1);
    });

    it('eclipse total de Sol del 14/12/2020: Sol y Luna juntos a 23° de Sagitario', function () {
        $carta = calcularAstro('2020-12-14T16:13:00Z', -39.0, -68.0, false);

        expect($carta['sol'])->toEqualWithDelta(263.1, 0.3)
            ->and($carta['luna'])->toEqualWithDelta($carta['sol'], 0.5)
            ->and($carta['ascendente'])->toBeNull();
    });

    it('equinoccio de marzo de 2000: el Sol entra en Aries (0°)', function () {
        $sol = calcularAstro('2000-03-20T07:35:00Z', 0, 0, false)['sol'];

        expect(min($sol, 360 - $sol))->toBeLessThan(0.05);
    });

    it('rechaza argumentos que no son números (no hay shell que inyectar)', function () {
        $proceso = Process::path(base_path())->run(['node', 'resources/astro/calcular.mjs', '1990; rm -rf /', 'x', 'y', '1']);

        expect($proceso->exitCode())->toBe(2);
    });
});

describe('la hora local → UTC, con la zona horaria histórica', function () {
    it('Formosa en enero de 1990 tenía horario de verano: UTC−2, no UTC−3', function () {
        $utc = app(CartaAstralService::class)->instanteLocal('1990-01-15', '14:30', 'America/Argentina/Cordoba')->utc();

        expect($utc->format('H:i'))->toBe('16:30');
    });

    it('en julio de 1990, la misma ciudad estaba en UTC−3', function () {
        $utc = app(CartaAstralService::class)->instanteLocal('1990-07-15', '14:30', 'America/Argentina/Cordoba')->utc();

        expect($utc->format('H:i'))->toBe('17:30');
    });

    it('una hora que no existió (el salto al horario de verano) se rechaza', function () {
        // 30/12/2007: en Buenos Aires el reloj pasó de 00:00 a 01:00
        app(CartaAstralService::class)->instanteLocal('2007-12-30', '00:30', 'America/Argentina/Buenos_Aires');
    })->throws(ValidationException::class);
});

describe('el flujo en la sala', function () {
    beforeEach(function () {
        $this->seed(CiudadSeeder::class);
        Event::fake([DatosNacimientoRecibidos::class, CartaAstralCalculada::class, PlanetaSenalado::class]);
        $this->consulta = Consulta::factory()->create();
        $this->lectura = Lectura::factory()->for($this->consulta)->tipo(TipoLectura::CartaAstral)->create(); // esperando datos
        $this->formosa = Ciudad::firstWhere('nombre', 'Formosa');
    });

    function enviarNacimiento(array $datos)
    {
        return test()->actingAs(test()->consulta->consultante)->postJson(rutaLectura('nacimiento', test()->lectura), $datos + [
            'fecha' => '1990-01-15', 'hora' => '14:30', 'ciudad_id' => test()->formosa->id,
        ]);
    }

    it('calcula, guarda y avisa: los datos de nacimiento NO viajan por el canal', function () {
        enviarNacimiento([])->assertNoContent();

        $lectura = $this->lectura->fresh();
        expect($lectura->estado)->toBe(EstadoLectura::Calculada)
            ->and($lectura->datos_nacimiento)->toMatchArray(['fecha' => '1990-01-15', 'hora' => '14:30', 'utc' => '1990-01-15T16:30:00Z'])
            ->and($lectura->resultado['puntos']['sol']['signo'])->toBe('capricornio') // 15 de enero
            ->and($lectura->resultado['puntos'])->toHaveKeys(['sol', 'luna', 'ascendente']);

        Event::assertDispatched(DatosNacimientoRecibidos::class, fn ($e) => $e->broadcastWith() === ['lectura' => $lectura->id]);
        Event::assertDispatched(CartaAstralCalculada::class, function ($e) use ($lectura) {
            $json = json_encode($e->broadcastWith());

            // == y no ===: MySQL reordena las claves de las columnas JSON; el contenido es el mismo
            return $e->broadcastWith()['resultado'] == $lectura->resultado
                && ! str_contains($json, '1990') && ! str_contains($json, '14:30') && ! str_contains($json, 'Formosa');
        });
    });

    it('sin hora: Sol y Luna (aproximada), sin Ascendente', function () {
        enviarNacimiento(['hora' => null, 'hora_desconocida' => true])->assertNoContent();

        $puntos = $this->lectura->fresh()->resultado['puntos'];
        expect($puntos)->toHaveKeys(['sol', 'luna'])->not->toHaveKey('ascendente')
            ->and($puntos['luna']['aproximada'])->toBeTrue()
            ->and($this->lectura->fresh()->resultado['hora_conocida'])->toBeFalse();
    });

    it('valida los datos', function (array $datos, string $campo) {
        enviarNacimiento($datos)->assertUnprocessable()->assertJsonValidationErrors($campo);
    })->with([
        'fecha futura' => [['fecha' => '2999-01-01'], 'fecha'],
        'hora con texto' => [['hora' => '14:30; rm -rf /'], 'hora'],
        'ciudad inexistente' => [['ciudad_id' => 9999], 'ciudad_id'],
    ]);

    it('solo la consultante carga sus datos, una sola vez', function () {
        $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('nacimiento', $this->lectura), [
            'fecha' => '1990-01-15', 'hora' => '14:30', 'ciudad_id' => $this->formosa->id,
        ])->assertForbidden();

        enviarNacimiento([])->assertNoContent();
        enviarNacimiento([])->assertConflict();
    });

    it('la tarotista señala un punto y viaja solo cuál', function () {
        enviarNacimiento([]);

        $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('senalar', $this->lectura), ['planeta' => 'luna'])->assertNoContent();
        Event::assertDispatched(PlanetaSenalado::class, fn ($e) => $e->broadcastWith() === ['lectura' => $this->lectura->id, 'planeta' => 'luna']);

        $this->actingAs($this->consulta->consultante)->postJson(rutaLectura('senalar', $this->lectura), ['planeta' => 'sol'])->assertForbidden();
        $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('senalar', $this->lectura), ['planeta' => 'pluton'])->assertUnprocessable();
    });

    it('la carta calculada se puede cerrar y vuelve en /estado al recargar', function () {
        enviarNacimiento([]);

        $this->actingAs($this->consulta->consultante)->getJson(route('consultas.estado', $this->consulta))
            ->assertOk()
            ->assertJsonPath('lectura.tipo', 'carta_astral')
            ->assertJsonPath('lectura.resultado.puntos.sol.signo', 'capricornio')
            ->assertJsonPath('lectura.nacimiento.lugar', 'Formosa, Formosa');

        $this->actingAs($this->consulta->tarotista)->postJson(rutaLectura('cerrar', $this->lectura))->assertNoContent();
        expect($this->lectura->fresh()->estado)->toBe(EstadoLectura::Cerrada);
    });

    it('nadie de afuera ve los datos de nacimiento', function () {
        enviarNacimiento([]);

        $this->actingAs(User::factory()->tarotista()->create())->getJson(route('consultas.estado', $this->consulta))->assertForbidden();
    });
});

it('Signo: de la longitud al signo y al grado', function () {
    expect(Signo::deLongitud(0))->toBe(Signo::Aries)
        ->and(Signo::deLongitud(359.9))->toBe(Signo::Piscis)
        ->and(Signo::deLongitud(101.6355))->toBe(Signo::Cancer)
        ->and(Signo::gradoEnSigno(101.6355))->toBe("11°38'")
        ->and(Signo::Geminis->nombre())->toBe('Géminis');
});
