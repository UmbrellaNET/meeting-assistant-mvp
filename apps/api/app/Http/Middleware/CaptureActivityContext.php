<?php

namespace App\Http\Middleware;

use App\Support\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureActivityContext
{
    public function __construct(private ActivityContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->capture($request);

        return $next($request);
    }
}
