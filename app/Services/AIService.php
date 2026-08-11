<?php

namespace App\Services;

use App\Models\AiContent;
use App\Models\BlogPost;
use App\Models\JobPost;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    /**
     * Crawl source URLs and extract job postings via OpenAI.
     *
     * @param  array<int, string>  $sourceUrls
     * @param  array<int, string>  $keywords
     * @return array<int, array<string, mixed>>
     */
    public function crawlJobs(array $sourceUrls, array $keywords = []): array
    {
        if (! $this->isEnabled()) {
            Log::warning('AI crawler skipped: AI is disabled.');

            return [];
        }

        $allJobs = [];

        foreach ($sourceUrls as $sourceUrl) {
            try {
                $content = $this->fetchPageContent($sourceUrl);
                if ($content === '') {
                    continue;
                }

                $keywordText = empty($keywords) ? '' : ' Keywords to focus on: '.implode(', ', $keywords).'.';
                $prompt = 'You are an Iranian recruitment data extractor. Extract job postings from the following content. Return ONLY a JSON array. Each item must have: title, company_name, description, province, city, job_category, registration_deadline (YYYY-MM-DD or null), exam_date (YYYY-MM-DD or null), registration_link (URL or null).'.$keywordText.' Content: '.$content;

                $raw = $this->chat($prompt);
                $items = $this->parseJsonArray($raw);

                $validJobs = [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if (blank($item['title'] ?? null) || blank($item['company_name'] ?? null) || blank($item['description'] ?? null)) {
                        continue;
                    }
                    $validJobs[] = [
                        'title' => (string) $item['title'],
                        'company_name' => (string) $item['company_name'],
                        'description' => (string) $item['description'],
                        'province' => $item['province'] ?? null,
                        'city' => $item['city'] ?? null,
                        'job_category' => $item['job_category'] ?? null,
                        'registration_deadline' => $item['registration_deadline'] ?? null,
                        'exam_date' => $item['exam_date'] ?? null,
                        'registration_link' => $item['registration_link'] ?? null,
                        'source_url' => $sourceUrl,
                    ];
                }

                if ($validJobs === []) {
                    continue;
                }

                AiContent::query()->create([
                    'type' => 'job_crawl',
                    'prompt' => $prompt,
                    'generated_content' => json_encode($validJobs, JSON_UNESCAPED_UNICODE),
                    'reviewed_by' => null,
                    'status' => 'pending',
                    'metadata' => [
                        'source_url' => $sourceUrl,
                        'keywords' => $keywords,
                        'jobs_count' => count($validJobs),
                    ],
                ]);

                foreach ($validJobs as $jobData) {
                    JobPost::query()->create([
                        'title' => $jobData['title'],
                        'company_name' => $jobData['company_name'],
                        'description' => $jobData['description'],
                        'province' => $jobData['province'],
                        'city' => $jobData['city'],
                        'job_category' => $jobData['job_category'],
                        'registration_deadline' => $jobData['registration_deadline'],
                        'exam_date' => $jobData['exam_date'],
                        'registration_link' => $jobData['registration_link'],
                        'source_url' => 'ai_crawler',
                        'status' => 'pending',
                        'is_featured' => false,
                        'created_by' => null,
                    ]);
                }

                $allJobs = array_merge($allJobs, $validJobs);
            } catch (\Throwable $e) {
                Log::error('Job crawl failed for source', [
                    'url' => $sourceUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $allJobs;
    }

    /**
     * Generate a Persian blog post draft via OpenAI.
     *
     * @return array{title: string, slug: string, excerpt: ?string, content: string, category: string, meta_title: ?string, meta_description: ?string}
     */
    public function generateBlogPost(string $topic): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $prompt = 'Write a 500-word Persian article about '.$topic.' for Iranian job seekers. Include practical tips. Return JSON: {title, slug, excerpt, content, category, meta_title, meta_description}';

        $raw = $this->chat($prompt);
        $data = $this->parseJsonObject($raw);

        if (blank($data['title'] ?? null) || blank($data['content'] ?? null)) {
            throw new \RuntimeException('پاسخ AI نامعتبر است.');
        }

        $slug = $data['slug'] ?? Str::slug($data['title']);
        if ($slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
            $slug = Str::slug($data['title']) ?: 'ai-post-'.Str::random(6);
        }

        return [
            'title' => (string) $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => (string) $data['content'],
            'category' => (string) ($data['category'] ?? 'عمومی'),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    /**
     * Create AiContent + draft BlogPost from a topic.
     *
     * @return array{preview: array<string, mixed>, ai_content_id: int, blog_post_id: int}
     */
    public function generateAndStoreBlogPost(string $topic, int $createdBy): array
    {
        $this->ensureWithinDailyLimit();

        $prompt = 'Write a 500-word Persian article about '.$topic.' for Iranian job seekers. Include practical tips. Return JSON: {title, slug, excerpt, content, category, meta_title, meta_description}';
        $generated = $this->generateBlogPost($topic);

        $aiContent = AiContent::query()->create([
            'type' => 'blog_post',
            'prompt' => $prompt,
            'generated_content' => json_encode($generated, JSON_UNESCAPED_UNICODE),
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => ['topic' => $topic],
        ]);

        $uniqueSlug = app(BlogPostService::class)->generateSlug($generated['slug']);

        $blogPost = BlogPost::query()->create([
            'title' => $generated['title'],
            'slug' => $uniqueSlug,
            'content' => $generated['content'],
            'excerpt' => $generated['excerpt'],
            'category' => $generated['category'],
            'meta_title' => $generated['meta_title'],
            'meta_description' => $generated['meta_description'],
            'status' => 'draft',
            'ai_content_id' => $aiContent->id,
            'created_by' => $createdBy,
        ]);

        return [
            'preview' => $generated,
            'ai_content_id' => $aiContent->id,
            'blog_post_id' => $blogPost->id,
        ];
    }

    /**
     * Advisory resume improvements — never auto-applied.
     *
     * @param  array<string, mixed>  $resumeData
     * @return array{suggestions: array<int, array{section: string, suggestion: string, priority: string}>, ai_content_id: int}
     */
    public function suggestResumeImprovements(array $resumeData, string $targetJob): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $this->ensureWithinDailyLimit('resume_tip', (int) Setting::get('ai_resume_limit_per_day', 5));

        $prompt = 'You are an expert Iranian HR consultant. Review this resume for a '.$targetJob.' position in Iran. The resume is in Persian. Provide 5 specific, actionable improvements. Focus on:
1. Missing skills or keywords for ATS
2. Weak or vague descriptions that need quantification
3. Formatting suggestions
4. Section order recommendations
5. Language and tone improvements

Return JSON array: [{section: "personal|education|experience|skills|summary|languages", suggestion: "string", priority: "high|medium|low"}]

Resume JSON:
'.json_encode($resumeData, JSON_UNESCAPED_UNICODE);

        $raw = $this->chat($prompt);
        $items = $this->parseJsonArray($raw);
        $validSections = ['personal', 'education', 'experience', 'skills', 'summary', 'languages'];
        $validPriority = ['high', 'medium', 'low'];

        $suggestions = [];
        foreach ($items as $item) {
            if (! is_array($item) || blank($item['suggestion'] ?? null)) {
                continue;
            }

            $section = (string) ($item['section'] ?? 'summary');
            if (! in_array($section, $validSections, true)) {
                $section = 'summary';
            }

            $priority = strtolower((string) ($item['priority'] ?? 'medium'));
            if (! in_array($priority, $validPriority, true)) {
                $priority = 'medium';
            }

            $suggestions[] = [
                'section' => $section,
                'suggestion' => (string) $item['suggestion'],
                'priority' => $priority,
            ];
        }

        if ($suggestions === []) {
            throw new \RuntimeException('پاسخ AI برای پیشنهاد رزومه نامعتبر است.');
        }

        $aiContent = AiContent::query()->create([
            'type' => 'resume_tip',
            'prompt' => $prompt,
            'generated_content' => json_encode($suggestions, JSON_UNESCAPED_UNICODE),
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => [
                'target_job' => $targetJob,
                'suggestions_count' => count($suggestions),
            ],
        ]);

        return [
            'suggestions' => $suggestions,
            'ai_content_id' => $aiContent->id,
        ];
    }

    /**
     * Write a Persian professional summary for the resume builder.
     *
     * @param  array<string, mixed>  $context
     * @return array{suggestion: string, ai_content_id: int}
     */
    public function writeResumeSummary(array $context): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $this->ensureWithinDailyLimit('resume_tip', (int) Setting::get('ai_resume_limit_per_day', 5));

        $title = (string) ($context['title'] ?? $context['target_job'] ?? 'کارشناس');
        $prompt = 'Write a compelling 2-3 sentence professional resume summary in Persian (Farsi) for an Iranian job seeker. Be concise and ATS-friendly. Return JSON object: {"suggestion":"..."}. Context: '.json_encode($context, JSON_UNESCAPED_UNICODE).' Job title/target: '.$title;

        $raw = $this->chat($prompt);
        $obj = $this->parseJsonObject($raw);
        $suggestion = trim((string) ($obj['suggestion'] ?? $obj['summary'] ?? ''));
        if ($suggestion === '') {
            $suggestion = trim($this->stripCodeFences($raw));
        }
        if ($suggestion === '' || str_starts_with($suggestion, '{')) {
            throw new \RuntimeException('پاسخ AI برای خلاصه رزومه نامعتبر است.');
        }

        $aiContent = AiContent::query()->create([
            'type' => 'resume_tip',
            'prompt' => $prompt,
            'generated_content' => $suggestion,
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => ['field' => 'summary'],
        ]);

        return ['suggestion' => $suggestion, 'ai_content_id' => $aiContent->id];
    }

    /**
     * Enhance a single experience description.
     *
     * @return array{enhanced: string, ai_content_id: int}
     */
    public function enhanceExperienceDescription(string $title, string $description): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $this->ensureWithinDailyLimit('resume_tip', (int) Setting::get('ai_resume_limit_per_day', 5));

        $prompt = 'Enhance this Persian job experience for an ATS-friendly Iranian resume. Use action verbs and quantify achievements when possible. Keep 3-4 short bullet lines separated by newline. Return JSON: {"enhanced":"..."}. Title: '.$title."\nDescription: ".$description;

        $raw = $this->chat($prompt);
        $obj = $this->parseJsonObject($raw);
        $enhanced = trim((string) ($obj['enhanced'] ?? $obj['description'] ?? ''));
        if ($enhanced === '') {
            $enhanced = trim($this->stripCodeFences($raw));
        }
        if ($enhanced === '' || str_starts_with($enhanced, '{')) {
            throw new \RuntimeException('پاسخ AI برای بهبود سابقه شغلی نامعتبر است.');
        }

        $aiContent = AiContent::query()->create([
            'type' => 'resume_tip',
            'prompt' => $prompt,
            'generated_content' => $enhanced,
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => ['field' => 'experience'],
        ]);

        return ['enhanced' => $enhanced, 'ai_content_id' => $aiContent->id];
    }

    /**
     * Suggest skills for the Iranian job market.
     *
     * @param  array<string, mixed>  $context
     * @return array{skills: array<int, string>, ai_content_id: int}
     */
    public function suggestResumeSkills(array $context): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $this->ensureWithinDailyLimit('resume_tip', (int) Setting::get('ai_resume_limit_per_day', 5));

        $prompt = 'Suggest 8-12 relevant skills for an Iranian job seeker. Mix Persian and English terms as common in Iran job ads. Return JSON: {"skills":["..."]}. Context: '.json_encode($context, JSON_UNESCAPED_UNICODE);

        $raw = $this->chat($prompt);
        $obj = $this->parseJsonObject($raw);
        $skills = [];
        if (isset($obj['skills']) && is_array($obj['skills'])) {
            foreach ($obj['skills'] as $skill) {
                if (is_string($skill) && trim($skill) !== '') {
                    $skills[] = Str::limit(trim($skill), 50, '');
                }
            }
        }

        if ($skills === []) {
            throw new \RuntimeException('پاسخ AI برای پیشنهاد مهارت نامعتبر است.');
        }

        $skills = array_values(array_unique($skills));

        $aiContent = AiContent::query()->create([
            'type' => 'resume_tip',
            'prompt' => $prompt,
            'generated_content' => json_encode($skills, JSON_UNESCAPED_UNICODE),
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => ['field' => 'skills', 'count' => count($skills)],
        ]);

        return ['skills' => $skills, 'ai_content_id' => $aiContent->id];
    }

    /**
     * Generate exam questions into AiContent only (Option A — no questions table until approve).
     *
     * @return array{ai_content_id: int, preview: array<int, array<string, mixed>>, count: int}
     */
    public function generateQuestions(string $subject, string $difficulty, int $count, int $examId): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('سرویس هوش مصنوعی غیرفعال است.');
        }

        $this->ensureWithinDailyLimit('exam_question', (int) Setting::get('ai_question_limit_per_day', 20));

        $count = max(1, min(20, $count));
        $prompt = "Generate {$count} multiple-choice questions in Persian for Iranian recruitment exam. Subject: {$subject}. Difficulty: {$difficulty}.
Requirements:
- Each question has exactly 4 options (A, B, C, D)
- Only ONE correct answer
- Include explanation for why the correct answer is right
- Questions should be relevant to Iranian government/private recruitment exams
- Support mathematical/chemical formulas using LaTeX notation where needed

Return JSON array: [{question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, subject, difficulty}]";

        $raw = $this->chat($prompt);
        $items = $this->parseJsonArray($raw);

        $validSubjects = ['math', 'literature', 'islamic', 'english', 'chemistry', 'physics', 'iq', 'general'];
        $validDifficulty = ['easy', 'medium', 'hard'];
        $questions = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $correct = strtolower((string) ($item['correct_answer'] ?? ''));
            if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                continue;
            }

            if (blank($item['question_text'] ?? null)
                || blank($item['option_a'] ?? null)
                || blank($item['option_b'] ?? null)
                || blank($item['option_c'] ?? null)
                || blank($item['option_d'] ?? null)
            ) {
                continue;
            }

            $qSubject = strtolower((string) ($item['subject'] ?? $subject));
            if (! in_array($qSubject, $validSubjects, true)) {
                $qSubject = in_array($subject, $validSubjects, true) ? $subject : 'general';
            }

            $qDiff = strtolower((string) ($item['difficulty'] ?? $difficulty));
            if (! in_array($qDiff, $validDifficulty, true)) {
                $qDiff = in_array($difficulty, $validDifficulty, true) ? $difficulty : 'medium';
            }

            $questions[] = [
                'question_text' => (string) $item['question_text'],
                'option_a' => (string) $item['option_a'],
                'option_b' => (string) $item['option_b'],
                'option_c' => (string) $item['option_c'],
                'option_d' => (string) $item['option_d'],
                'correct_answer' => $correct,
                'explanation' => $item['explanation'] ?? null,
                'subject' => $qSubject,
                'difficulty' => $qDiff,
            ];
        }

        if ($questions === []) {
            throw new \RuntimeException('هیچ سوال معتبری از AI دریافت نشد.');
        }

        // Option A: فقط AiContent — جدول questions بعد از تایید اپراتور پر می‌شود
        $aiContent = AiContent::query()->create([
            'type' => 'exam_question',
            'prompt' => $prompt,
            'generated_content' => json_encode($questions, JSON_UNESCAPED_UNICODE),
            'reviewed_by' => null,
            'status' => 'pending',
            'metadata' => [
                'exam_id' => $examId,
                'subject' => $subject,
                'difficulty' => $difficulty,
                'count' => count($questions),
                'generated_questions' => $questions,
            ],
        ]);

        return [
            'ai_content_id' => $aiContent->id,
            'preview' => $questions,
            'count' => count($questions),
        ];
    }

    public function ensureWithinDailyLimit(?string $type = null, ?int $typeLimit = null): void
    {
        $dailyLimit = (int) Setting::get('ai_daily_limit', 50);
        $todayTotal = AiContent::query()->whereDate('created_at', today())->count();

        if ($todayTotal >= $dailyLimit) {
            throw new \RuntimeException('سقف استفاده روزانه AI تکمیل شده است.');
        }

        if ($type && $typeLimit !== null) {
            $typeCount = AiContent::query()
                ->where('type', $type)
                ->whereDate('created_at', today())
                ->count();

            if ($typeCount >= $typeLimit) {
                throw new \RuntimeException('سقف استفاده روزانه AI تکمیل شده است.');
            }
        }
    }

    public function isEnabled(): bool
    {
        $enabled = Setting::get('ai_enabled', 'true');

        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    protected function apiKey(): ?string
    {
        $key = Setting::getFilled('ai_api_key', env('OPENAI_API_KEY', ''));

        return $key !== '' && $key !== null ? (string) $key : null;
    }

    protected function model(): string
    {
        return (string) Setting::get('ai_model', 'gpt-4');
    }

    protected function fetchPageContent(string $url): string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'JobAzmoonBot/1.0'])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Job crawl fetch failed', ['url' => $url, 'status' => $response->status()]);

                return '';
            }

            $body = strip_tags($response->body());
            $body = preg_replace('/\s+/u', ' ', $body) ?? '';

            return Str::limit(trim($body), 12000, '');
        } catch (\Throwable $e) {
            Log::warning('Job crawl fetch exception', ['url' => $url, 'error' => $e->getMessage()]);

            return '';
        }
    }

    protected function chat(string $prompt): string
    {
        $apiKey = $this->apiKey();
        if (! $apiKey) {
            throw new \RuntimeException('کلید API هوش مصنوعی تنظیم نشده است.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model(),
                'messages' => [
                    ['role' => 'system', 'content' => 'You return valid JSON only. No markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI API error', ['body' => $response->body()]);
            throw new \RuntimeException('خطا در ارتباط با سرویس هوش مصنوعی.');
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '');
    }

    /**
     * @return array<int, mixed>
     */
    protected function parseJsonArray(string $raw): array
    {
        $cleaned = $this->stripCodeFences($raw);
        $decoded = json_decode($cleaned, true);

        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (is_array($decoded) && isset($decoded['jobs']) && is_array($decoded['jobs'])) {
            return $decoded['jobs'];
        }

        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            return $decoded['questions'];
        }

        if (is_array($decoded) && isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
            return $decoded['suggestions'];
        }

        if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
            return $decoded['data'];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJsonObject(string $raw): array
    {
        $cleaned = $this->stripCodeFences($raw);
        $decoded = json_decode($cleaned, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function stripCodeFences(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m)) {
            return trim($m[1]);
        }

        return $raw;
    }
}
