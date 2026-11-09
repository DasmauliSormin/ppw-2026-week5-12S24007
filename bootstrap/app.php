<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Pengecualian CSRF Token untuk rute SOAP
        $middleware->validateCsrfTokens(except: [
            'soap/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Paksa rute /api/* merender format JSON
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Error Validasi Input (422 Unprocessable Content)
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'type'     => url('/docs/errors/validation-failed'),
                    'title'    => 'Unprocessable Content',
                    'status'   => 422,
                    'detail'   => 'Satu atau lebih atribut input tidak valid.',
                    'instance' => $request->getRequestUri(),
                    'errors'   => $e->errors(),
                ], 422)->header('Content-Type', 'application/problem+json');
            }
        });

        // Error Auth / Bearer Token Hilang (401 Unauthorized)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'type'     => url('/docs/errors/unauthenticated'),
                    'title'    => 'Unauthorized',
                    'status'   => 401,
                    'detail'   => 'Akses ditolak. Lampirkan Bearer Token yang valid.',
                    'instance' => $request->getRequestUri(),
                ], 401)->header('Content-Type', 'application/problem+json');
            }
        });

        // Error Endpoint / Resource Tidak Ditemukan (404 Not Found)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'type'     => url('/docs/errors/endpoint-not-found'),
                    'title'    => 'Not Found',
                    'status'   => 404,
                    'detail'   => 'URI endpoint atau resource tidak ditemukan.',
                    'instance' => $request->getRequestUri(),
                ], 404)->header('Content-Type', 'application/problem+json');
            }
        });
    })->create();