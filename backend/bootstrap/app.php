<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception): bool => $request->is('api/*')
                || $request->expectsJson()
        );

        // Mencari "siapa" pelakunya. Mencatat log tidak boleh membuat request ikut error,
        // jadi kalau pencarian gagal (misalnya DB mati) hasilnya 'unknown'.
        $actor = function (Request $request) {
            try {
                return $request->user('sanctum')?->id ?? 'guest';
            } catch (\Throwable) {
                return 'unknown';
            }
        };

        // WARNING: input tidak valid. Yang dicatat hanya NAMA field yang salah, bukan isinya.
        $exceptions->render(function (ValidationException $e, Request $request) use ($actor) {
            if (! $request->attributes->get('log_already_written')) {
                Log::warning('request.validation_failed', [
                    'actor' => $actor($request),
                    'action' => $request->method().' /'.$request->path(),
                    'reason' => 'invalid_input',
                    'invalid_fields' => array_keys($e->errors()),
                ]);
            }

            return null; // null = lanjutkan ke respons 422 bawaan Laravel
        });

        // ERROR: gagal berhubungan dengan / memakai basis data.
        // Pesan asli QueryException berisi SQL beserta nilainya (bisa memuat email/password),
        // jadi TIDAK dicatat. stop() mencegah Laravel mencatat versi lengkapnya.
        $exceptions->report(function (QueryException $e) use ($actor) {
            $request = request();

            Log::error('database.query_failed', [
                'actor' => $actor($request),
                'action' => $request->method().' /'.$request->path(),
                'reason' => 'database_error',
                'db_error_code' => $e->getCode(),
            ]);
        })->stop();
    })->create();