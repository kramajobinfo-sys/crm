<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [\App\Http\Middleware\ForceJsonResponse::class]);
        $middleware->alias([
            'jwt.auth'      => \App\Http\Middleware\JwtAuthenticate::class,
            'jwt.refresh'   => \App\Http\Middleware\JwtRefresh::class,
            'portal.auth'   => \App\Http\Middleware\PortalAuthenticate::class,
            'role'          => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'    => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'scope.company' => \App\Http\Middleware\ScopeCompany::class,
            'audit'         => \App\Http\Middleware\AuditRequest::class,
            'feature'       => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'platform.admin'=> \App\Http\Middleware\EnsurePlatformAdmin::class,
            '2fa'           => \App\Http\Middleware\RequireTwoFactor::class,
        ]);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, Request $r) {
            if ($r->is('api/*')) return response()->json(['success'=>false,'message'=>'Validation failed','errors'=>$e->errors()], 422);
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) {
            if ($r->is('api/*')) return response()->json(['success'=>false,'message'=>'Unauthenticated'], 401);
        });
        $exceptions->render(function (ModelNotFoundException $e, Request $r) {
            if ($r->is('api/*')) return response()->json(['success'=>false,'message'=>'Not found'], 404);
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $r) {
            if ($r->is('api/*')) return response()->json(['success'=>false,'message'=>'Not found'], 404);
        });
        $exceptions->render(function (\Throwable $e, Request $r) {
            if ($r->is('api/*') && !config('app.debug')) return response()->json(['success'=>false,'message'=>'Server error'], 500);
        });
    })
    ->create();
