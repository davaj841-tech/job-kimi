<?php

namespace App\Http\Controllers;

use App\Services\SEOService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(SEOService $seoService): Response
    {
        return $seoService->generateSitemap();
    }
}
