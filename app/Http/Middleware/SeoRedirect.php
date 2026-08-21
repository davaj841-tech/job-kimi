<?php

namespace App\Http\Middleware;

use App\Services\Seo\RedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoRedirect
{
    public function __construct(protected RedirectService $redirectService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $redirect = $this->redirectService->findRedirect($request->getPathInfo());

        if ($redirect) {
            $response = $this->redirectService->handleRedirect($redirect);
            if ($response) {
                return $response;
            }
        }

        return $next($request);
    }
}
