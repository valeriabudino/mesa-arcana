<?php

use App\Enums\EstadoLectura;
use App\Enums\TipoMensaje;
use App\Events\BorradorInterpretacion;
use App\Events\CartaElegida;
use App\Events\CartaRevelada;
use App\Events\InterpretacionPublicada;
use App\Events\MazoMezclado;
use App\Events\MensajeEnviado;
use App\Interpretacion\Interpretador;
use App\Interpretacion\InterpretadorIA;
use App\Jobs\GenerarInterpretacion;
use App\Models\Consulta;
use App\Models\Lectura;
use App\Models\Mensaje;
use App\Models\Pregunta;
use App\Services\InterpretacionService;
use Database\Seeders\CartaSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;

beforeEach(function () {
    $this->seed(CartaSeeder::class);
    Event::fake([MazoMezclado::class, CartaElegida::class, CartaRevelada::class, MensajeEnviado::class, BorradorInterpretacion::class, InterpretacionPublicada::class]);
    $this->consulta = Consulta::factory()->create();
    $this->consulta->consultante->update(['name' => 'Ramona Secreta']);
    $this->tarotista = $this->consulta->tarotista;
    $this->consultante = $this->consulta->consultante;
    $this->lectura = Lectura::factory()->for($this->consulta)->create([
        'pregunta_id' => Pregunta::factory()->create(['texto' => '¿Qué necesito saber hoy?'])->id,
    ]);
});

/** Mezcla, elige las 3 y revela las que se pidan. */
function tiradaHastaRevelar(array $revelar = [0, 1, 2]): void
{
    test()->actingAs(test()->tarotista)->postJson(rutaLectura('mezclar', test()->lectura));
    foreach ([3, 9, 27] as $indice) {
        test()->actingAs(test()->consultante)->postJson(rutaLectura('elegir', test()->lectura), ['indice_abanico' => $indice]);
    }
    foreach ($revelar as $posicion) {
        test()->actingAs(test()->tarotista)->postJson(rutaLectura('revelar', test()->lectura), ['posicion' => $posicion]);
    }
}

/** Un interpretador de mentira: escribe en dos tandas (o falla), y cuenta cuántas veces lo llamaron. */
function interpretadorFalso(bool $falla = false): object
{
    $falso = new class($falla) implements Interpretador
    {
        public int $llamadas = 0;

        public ?array $recibio = null;

        public function __construct(private bool $falla) {}

        public function interpretar(array $lectura, callable $alAvanzar): string
        {
            $this->llamadas++;
            $this->recibio = $lectura;
            if ($this->falla) {
                throw new RuntimeException('Groq no responde');
            }
            $alAvanzar('Las cartas');

            return 'Las cartas hablan de un camino nuevo.';
        }

        public function fuente(): string
        {
            return 'ia';
        }
    };
    app()->instance(Interpretador::class, $falso);

    return $falso;
}

it('con la última carta revelada se encola la interpretación (no antes)', function () {
    Queue::fake();

    tiradaHastaRevelar([0, 1]);
    Queue::assertNothingPushed();

    $this->actingAs($this->tarotista)->postJson(rutaLectura('revelar', $this->lectura), ['posicion' => 2]);
    Queue::assertPushed(GenerarInterpretacion::class, fn ($job) => $job->lecturaId === $this->lectura->id);
});

it('el borrador le llega en vivo SOLO a la tarotista, por su canal privado', function () {
    interpretadorFalso();
    tiradaHastaRevelar(); // el job corre en el momento (cola sync en los tests)

    $lectura = $this->lectura->fresh();
    expect($lectura->interpretacion_borrador)->toBe('Las cartas hablan de un camino nuevo.')
        ->and($lectura->interpretacion_fuente)->toBe('ia');
    Event::assertDispatchedTimes(BorradorInterpretacion::class, 2); // el avance y el final
    Event::assertDispatched(BorradorInterpretacion::class, fn ($e) => $e->broadcastOn()[0]->name === 'private-App.Models.User.'.$this->tarotista->id
        && $e->broadcastWith()['terminado'] === true);
    expect(Mensaje::where('tipo', TipoMensaje::Interpretacion)->exists())->toBeFalse(); // todavía no está publicada
});

it('a la IA le llega solo la lectura: cartas, tema y pregunta; nada de la persona', function () {
    $falso = interpretadorFalso();
    tiradaHastaRevelar();

    $json = json_encode($falso->recibio);
    expect($falso->recibio)->toHaveKeys(['tipo', 'tema', 'pregunta', 'cartas', 'respuesta'])
        ->and($falso->recibio['pregunta'])->toBe('¿Qué necesito saber hoy?')
        ->and($falso->recibio['cartas'])->toHaveCount(3)
        ->and($json)->not->toContain('Ramona')->not->toContain('@');
});

it('si la IA falla, el borrador sale de los significados de la base', function () {
    interpretadorFalso(falla: true);
    tiradaHastaRevelar();

    $lectura = $this->lectura->fresh(['cartas.carta']);
    expect($lectura->interpretacion_fuente)->toBe('base')
        ->and($lectura->interpretacion_borrador)->toContain($lectura->cartas->firstWhere('posicion', 0)->carta->nombre);
});

it('la misma pregunta con las mismas cartas sale de la caché, sin volver a llamar a la IA', function () {
    $falso = interpretadorFalso();
    tiradaHastaRevelar();
    $servicio = app(InterpretacionService::class);

    $servicio->generar($this->lectura->fresh());

    expect($falso->llamadas)->toBe(1);
});

it('el interpretador de Groq escribe en streaming con los límites del plan en el prompt', function () {
    $fake = Prism::fake([TextResponseFake::make()->withText('Hoy El Mago te invita a animarte.')]);
    $avances = [];

    $texto = (new InterpretadorIA('groq', 'llama-3.3-70b-versatile', 20, 0))->interpretar(
        ['tipo' => 'Sí o no', 'tema' => 'General', 'pregunta' => '¿Me conviene tomar la decisión?', 'respuesta' => 'Sí',
            'cartas' => [['posicion' => 'Respuesta', 'nombre' => 'El Mago', 'invertida' => false, 'significado' => 'Acción.', 'aclara' => null]]],
        function (string $parcial) use (&$avances) {
            $avances[] = $parcial;
        },
    );

    expect($texto)->toBe('Hoy El Mago te invita a animarte.')->and(count($avances))->toBeGreaterThan(1);
    $fake->assertRequest(function (array $requests) {
        $request = $requests[0];
        expect($request->systemPrompts()[0]->content)->toContain('rioplatense')->toContain('Nada de diagnósticos')->toContain('muerte')
            ->and($request->prompt())->toContain('El Mago')->toContain('Respuesta de la tirada de sí o no: Sí');
    });
});

describe('publicar', function () {
    beforeEach(function () {
        interpretadorFalso();
        tiradaHastaRevelar();
    });

    it('la tarotista publica el borrador editado y entra al chat de las dos', function () {
        $this->actingAs($this->tarotista)->postJson(rutaLectura('interpretacion', $this->lectura), ['texto' => 'Mi versión, corregida.'])
            ->assertNoContent();

        $mensaje = Mensaje::where('tipo', TipoMensaje::Interpretacion)->sole();
        expect($mensaje->contenido)->toBe('Mi versión, corregida.')
            ->and($mensaje->user_id)->toBe($this->tarotista->id)
            ->and($this->lectura->fresh()->interpretacion_publicada_at)->not->toBeNull();
        Event::assertDispatched(InterpretacionPublicada::class, fn ($e) => $e->broadcastOn()[0]->name === 'presence-consulta.'.$this->consulta->uuid
            && $e->broadcastWith()['contenido'] === 'Mi versión, corregida.');
    });

    it('la consultante no publica, y sin texto no se publica', function () {
        $this->actingAs($this->consultante)->postJson(rutaLectura('interpretacion', $this->lectura), ['texto' => 'Hola'])->assertForbidden();
        $this->actingAs($this->tarotista)->postJson(rutaLectura('interpretacion', $this->lectura), ['texto' => ''])->assertJsonValidationErrors('texto');
    });

    it('el borrador viaja en /estado solo para la tarotista, y nunca al serializar la lectura', function () {
        $this->actingAs($this->tarotista)->getJson(route('consultas.estado', $this->consulta))
            ->assertJsonPath('borrador.texto', 'Las cartas hablan de un camino nuevo.');
        $this->actingAs($this->consultante)->getJson(route('consultas.estado', $this->consulta))
            ->assertJsonMissingPath('borrador');
        expect($this->lectura->fresh()->toArray())->not->toHaveKey('interpretacion_borrador');
    });
});

it('antes de revelar todo no se publica', function () {
    tiradaHastaRevelar([0]);
    expect($this->lectura->fresh()->estado)->toBe(EstadoLectura::Revelando);

    $this->actingAs($this->tarotista)->postJson(rutaLectura('interpretacion', $this->lectura), ['texto' => 'Apurada'])->assertConflict();
});
