<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Http\Controllers\Api\BaseController;
use App\Models\ContentTemplate;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Services\Content\ContentGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GeneratedContentAdminController extends BaseController
{
    public function __construct(
        protected ContentGeneratorService $generator,
    ) {}

    public function dashboard(): JsonResponse
    {
        $tz = (string) config('content.timezone', 'Asia/Tehran');
        $start = now($tz)->startOfDay();
        $end = now($tz)->endOfDay();

        return $this->successResponse([
            'generated_today' => GeneratedContent::query()->whereBetween('created_at', [$start, $end])->whereNotIn('status', ['skipped', 'failed'])->count(),
            'published_today' => GeneratedContent::query()->whereBetween('published_at', [$start, $end])->where('status', 'published')->count(),
            'drafts' => GeneratedContent::query()->where('status', 'draft')->count(),
            'failed' => GeneratedContent::query()->where('status', 'failed')->count(),
            'skipped' => GeneratedContent::query()->where('status', 'skipped')->count(),
            'pending_publish' => GeneratedContent::query()->whereIn('status', ['draft', 'scheduled'])->count(),
            'max_per_day' => (int) config('content.max_articles_per_day', 1),
            'publish_mode' => config('content.publish_mode'),
            'enabled' => (bool) config('content.enabled'),
            'timezone' => $tz,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = GeneratedContent::query()->with(['jobPost:id,title,company_name'])->latest('id');
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (! in_array($status, ['draft', 'scheduled', 'published', 'failed', 'skipped'], true)) {
                return $this->errorResponse('وضعیت نامعتبر است.', 422);
            }
            $q->where('status', $status);
        }
        if ($request->filled('content_type')) {
            $type = $request->string('content_type')->toString();
            if (! ContentType::tryFrom($type)) {
                return $this->errorResponse('نوع محتوا نامعتبر است.', 422);
            }
            $q->where('content_type', $type);
        }
        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%");
            });
        }

        $rows = $q->paginate((int) $request->input('per_page', 20));

        return $this->successResponse([
            'data' => collect($rows->items())->map(fn (GeneratedContent $c) => $this->serialize($c))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
            'types' => collect(ContentType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ])->values(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $c = GeneratedContent::query()->with(['jobPost.source'])->find($id);
        if (! $c) {
            return $this->errorResponse('محتوا یافت نشد.', 404);
        }

        return $this->successResponse($this->serialize($c, true));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $c = GeneratedContent::query()->find($id);
        if (! $c) {
            return $this->errorResponse('محتوا یافت نشد.', 404);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:draft,scheduled,published,failed,skipped'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        if (isset($data['content'])) {
            // Strip dangerous tags from admin edits; keep basic formatting.
            $data['content'] = strip_tags($data['content'], '<p><br><h2><h3><h4><ul><ol><li><strong><em><a><blockquote>');
            if (preg_match('/\son[a-z]+\s*=/i', $data['content']) || preg_match('/javascript:/i', $data['content'])) {
                return $this->errorResponse('محتوای ناامن مجاز نیست.', 422);
            }
        }

        $c->update($data);

        return $this->successResponse($this->serialize($c->fresh()), 'به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $c = GeneratedContent::query()->find($id);
        if (! $c) {
            return $this->errorResponse('محتوا یافت نشد.', 404);
        }
        $c->delete();

        return $this->successResponse(null, 'حذف شد.');
    }

    public function regenerate(int $id): JsonResponse
    {
        $c = GeneratedContent::query()->find($id);
        if (! $c || ! $c->job_post_id) {
            return $this->errorResponse('امکان بازتولید وجود ندارد.', 422);
        }
        $job = JobPost::query()->with('source')->find($c->job_post_id);
        if (! $job) {
            return $this->errorResponse('آگهی یافت نشد.', 404);
        }
        $type = $c->content_type instanceof ContentType ? $c->content_type : ContentType::tryFrom((string) $c->content_type);
        $result = $this->generator->generateForJob($job, $type);

        return $this->successResponse([
            'outcome' => $result['outcome'],
            'error' => $result['error'],
            'content' => $result['content'] ? $this->serialize($result['content'], true) : null,
        ], 'بازتولید انجام شد.');
    }

    public function publish(int $id): JsonResponse
    {
        $c = GeneratedContent::query()->find($id);
        if (! $c) {
            return $this->errorResponse('محتوا یافت نشد.', 404);
        }

        $ok = $this->generator->publishContent($c);

        return $this->successResponse($this->serialize($c->fresh()), $ok ? 'منتشر شد.' : 'انتشار ناقص بود.');
    }

    public function unpublish(int $id): JsonResponse
    {
        $c = GeneratedContent::query()->find($id);
        if (! $c) {
            return $this->errorResponse('محتوا یافت نشد.', 404);
        }

        $this->generator->unpublishContent($c);

        return $this->successResponse($this->serialize($c->fresh()), 'به پیش‌نویس برگشت.');
    }

    public function generateNow(Request $request): JsonResponse
    {
        if ($request->boolean('seed_templates')) {
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ContentTemplateSeeder', '--force' => true]);
        }
        $stats = $this->generator->generateDaily(null, true);

        $message = 'تولید اجرا شد.';
        if (($stats['created'] + $stats['updated']) === 0) {
            $hint = $stats['errors'][0] ?? null;
            $message = match ($hint) {
                'max_articles_per_day_reached' => 'سقف تولید امروز پر است.',
                default => 'مقاله‌ای ساخته نشد. آگهی تأییدشده با منبع معتبر لازم است.',
            };
        }

        return $this->successResponse($stats, $message);
    }

    public function templates(): JsonResponse
    {
        $rows = ContentTemplate::query()->orderByDesc('priority')->get();

        return $this->successResponse($rows);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $t = ContentTemplate::query()->find($id);
        if (! $t) {
            return $this->errorResponse('قالب یافت نشد.', 404);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:190'],
            'title_template' => ['sometimes', 'string', 'max:500'],
            'content_template' => ['sometimes', 'string'],
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);
        $t->update($data);

        return $this->successResponse($t->fresh(), 'قالب ذخیره شد.');
    }

    public function settings(): JsonResponse
    {
        return $this->successResponse([
            'enabled' => (bool) config('content.enabled'),
            'daily_generation_enabled' => (bool) config('content.daily_generation_enabled'),
            'daily_generation_time' => config('content.daily_generation_time'),
            'publish_mode' => config('content.publish_mode'),
            'minimum_content_length' => (int) config('content.minimum_content_length'),
            'max_articles_per_day' => (int) config('content.max_articles_per_day'),
            'sync_to_blog' => (bool) config('content.sync_to_blog'),
            'note' => 'تغییر دائمی تنظیمات از طریق .env / config/content.php انجام می‌شود. انتشار فقط در لاراول است.',
        ]);
    }

    protected function serialize(GeneratedContent $c, bool $full = false): array
    {
        $row = [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'excerpt' => $c->excerpt,
            'content_type' => $c->content_type instanceof ContentType ? $c->content_type->value : $c->content_type,
            'content_type_label' => $c->content_type instanceof ContentType ? $c->content_type->label() : $c->content_type,
            'status' => $c->status instanceof ContentStatus ? $c->status->value : $c->status,
            'job_post_id' => $c->job_post_id,
            'job_title' => $c->jobPost?->title,
            'company_name' => $c->jobPost?->company_name,
            'blog_post_id' => $c->blog_post_id,
            'public_url' => $c->status === ContentStatus::Published || ($c->status instanceof ContentStatus && $c->status === ContentStatus::Published)
                ? $c->publicUrl()
                : null,
            'published_at' => $c->published_at?->toIso8601String(),
            'scheduled_for' => $c->scheduled_for?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'last_error' => $c->last_error,
            'generation_attempts' => $c->generation_attempts,
        ];
        if ($full) {
            $row['content'] = $c->content;
            $row['metadata'] = $c->metadata;
            $row['content_hash'] = $c->content_hash;
        }

        return $row;
    }
}
