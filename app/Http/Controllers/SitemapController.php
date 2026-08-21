<?php

namespace App\Http\Controllers;

use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(protected SitemapService $sitemapService) {}

    public function __invoke(): Response
    {
        return response($this->sitemapService->generateIndex(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function pages(): Response
    {
        return response($this->sitemapService->generatePages(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function jobs(): Response
    {
        return response($this->sitemapService->generateJobs(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function exams(): Response
    {
        return response($this->sitemapService->generateExams(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function articles(): Response
    {
        return response($this->sitemapService->generateArticles(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function blog(): Response
    {
        return response($this->sitemapService->generateBlog(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function files(): Response
    {
        return response($this->sitemapService->generateFiles(), 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
