<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $site = rtrim(config('app.url'), '/');
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api\nDisallow: /filament\nSitemap: {$site}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
