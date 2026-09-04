<?php

namespace App\Http\Controllers;

use App\Services\Seo\SeoHeadRenderer;
use App\Services\Seo\SeoRouteResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SpaController extends Controller
{
    /**
     * Known public SPA route prefixes (first segment or exact).
     *
     * @var list<string>
     */
    protected array $known = [
        '',
        'login',
        'forgot-password',
        'reset-password',
        'dashboard',
        'exams',
        'jobs',
        'blog',
        'articles',
        'pdfs',
        'my-purchases',
        'resumes',
        'wallet',
        'subscription',
        'profile',
        'notifications',
        'terms',
        'privacy',
        'about',
        'contact',
        'support',
        'leaderboard',
        'page',
        'payment',
    ];

    public function __invoke(Request $request, SeoRouteResolver $resolver, SeoHeadRenderer $headRenderer): Response
    {
        $path = trim($request->path(), '/');
        $status = $this->isKnownPath($path) ? 200 : 404;
        $seoPayload = $resolver->resolve($path);
        $seoHead = $headRenderer->render($seoPayload);

        return response()->view('spa', [
            'seoHead' => $seoHead,
            'seoPayload' => $seoPayload,
        ], $status);
    }

    protected function isKnownPath(string $path): bool
    {
        if ($path === '') {
            return true;
        }

        $first = Str::before($path, '/');

        return in_array($first, $this->known, true);
    }
}
