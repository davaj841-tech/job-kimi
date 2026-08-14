<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter bank of ORIGINAL practice questions (not copied from commercial sites).
 */
class PracticeQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'ریاضی', 'slug' => 'math', 'icon' => '🔢', 'sort_order' => 1],
            ['name' => 'ادبیات فارسی', 'slug' => 'literature', 'icon' => '📖', 'sort_order' => 2],
            ['name' => 'معارف اسلامی', 'slug' => 'islamic', 'icon' => '🕌', 'sort_order' => 3],
            ['name' => 'زبان انگلیسی', 'slug' => 'english', 'icon' => '🔤', 'sort_order' => 4],
            ['name' => 'هوش و استعداد', 'slug' => 'iq', 'icon' => '🧠', 'sort_order' => 5],
            ['name' => 'اطلاعات عمومی', 'slug' => 'general', 'icon' => '📰', 'sort_order' => 6],
            ['name' => 'شیمی', 'slug' => 'chemistry', 'icon' => '⚗️', 'sort_order' => 7],
            ['name' => 'فیزیک', 'slug' => 'physics', 'icon' => '⚛️', 'sort_order' => 8],
        ];

        foreach ($subjects as $s) {
            ExamSubject::query()->updateOrCreate(
                ['slug' => $s['slug']],
                [
                    'name' => $s['name'],
                    'icon' => $s['icon'],
                    'sort_order' => $s['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $category = ExamCategory::query()->first()
            ?? ExamCategory::query()->create([
                'name' => 'عمومی',
                'slug' => 'general',
            ]);

        $adminId = User::query()->where('role', 'admin')->value('id')
            ?? User::query()->value('id');

        if (! $adminId) {
            if ($this->command) {
                $this->command->error('PracticeQuestionsSeeder: no users found — seed AdminUserSeeder first.');
            }

            return;
        }

        $exam = Exam::query()->where('slug', 'practice-employment-bank')->first();
        if (! $exam) {
            $exam = Exam::query()->create([
                'title' => 'بانک تمرین استخدامی (نمونه تألیفی)',
                'slug' => 'practice-employment-bank',
                'description' => 'سوالات تمرینی تألیفی جاب‌آزمون برای آمادگی عمومی — جایگزین دفترچه رسمی نیست.',
                'category_id' => $category->id,
                'created_by' => $adminId,
                'status' => 'published',
                'is_free' => true,
                'price' => 0,
                'duration_minutes' => 60,
                'total_questions' => 0,
                'passing_score' => 50,
            ]);
        }

        $bank = [
            [
                'subject' => 'math',
                'q' => 'اگر قیمت کالایی ۲۰٪ افزایش و سپس ۲۰٪ کاهش یابد، نسبت به قیمت اول چه تغییری می‌کند؟',
                'a' => 'بدون تغییر', 'b' => '۴٪ کاهش', 'c' => '۴٪ افزایش', 'd' => '۲۰٪ کاهش',
                'correct' => 'b',
                'exp' => '۱٫۲ × ۰٫۸ = ۰٫۹۶؛ یعنی ۴٪ کاهش نسبت به مقدار اولیه.',
                'diff' => 'medium', 'year' => '1402',
            ],
            [
                'subject' => 'math',
                'q' => 'میانگین اعداد ۸، ۱۲ و ۱۶ چند است؟',
                'a' => '۱۰', 'b' => '۱۲', 'c' => '۱۴', 'd' => '۱۸',
                'correct' => 'b',
                'exp' => '(۸+۱۲+۱۶)/۳ = ۱۲.',
                'diff' => 'easy', 'year' => '1401',
            ],
            [
                'subject' => 'math',
                'q' => 'کدام عدد عدد اول نیست؟',
                'a' => '۲', 'b' => '۳', 'c' => '۹', 'd' => '۷',
                'correct' => 'c',
                'exp' => '۹ = ۳×۳؛ مرکب است.',
                'diff' => 'easy', 'year' => '1403',
            ],
            [
                'subject' => 'literature',
                'q' => 'مخفف «نمی‌دانم» در گفتار رسمی‌تر کدام است؟',
                'a' => 'ندونم', 'b' => 'نمی‌دانم', 'c' => 'نمیدونم', 'd' => 'نمدونم',
                'correct' => 'b',
                'exp' => 'شکل استاندارد نوشتاری «نمی‌دانم» است.',
                'diff' => 'easy', 'year' => '1400',
            ],
            [
                'subject' => 'literature',
                'q' => 'کدام آرایه در بیت «شب است و شاهد و شمع و شراب و شیرینی» برجسته‌تر است؟',
                'a' => 'جناس', 'b' => 'تلمیح', 'c' => 'واج‌آرایی', 'd' => 'تشبیه',
                'correct' => 'c',
                'exp' => 'تکرار صامت «ش» واج‌آرایی (همحروفی) است.',
                'diff' => 'medium', 'year' => '1402',
            ],
            [
                'subject' => 'islamic',
                'q' => 'تعداد ارکان نماز واجب روزانه چند است؟',
                'a' => '۳', 'b' => '۴', 'c' => '۵', 'd' => '۷',
                'correct' => 'c',
                'exp' => 'نمازهای یومیه پنج‌گانه هستند (صبح، ظهر، عصر، مغرب، عشا).',
                'diff' => 'easy', 'year' => '1401',
            ],
            [
                'subject' => 'islamic',
                'q' => 'کدام ماه، ماه روزه واجب است؟',
                'a' => 'رجب', 'b' => 'شعبان', 'c' => 'رمضان', 'd' => 'محرم',
                'correct' => 'c',
                'exp' => 'روزه ماه رمضان از فروع دین است.',
                'diff' => 'easy', 'year' => '1403',
            ],
            [
                'subject' => 'english',
                'q' => 'Choose the correct sentence:',
                'a' => 'He go to work every day.', 'b' => 'He goes to work every day.', 'c' => 'He going to work every day.', 'd' => 'He gone to work every day.',
                'correct' => 'b',
                'exp' => 'Third-person singular present simple takes -s/-es: goes.',
                'diff' => 'easy', 'year' => '1402',
            ],
            [
                'subject' => 'english',
                'q' => 'Synonym of “rapid” is closest to:',
                'a' => 'slow', 'b' => 'quick', 'c' => 'heavy', 'd' => 'quiet',
                'correct' => 'b',
                'exp' => 'Rapid means fast/quick.',
                'diff' => 'easy', 'year' => '1401',
            ],
            [
                'subject' => 'iq',
                'q' => 'ادامه الگو: ۲، ۴، ۸، ۱۶، ؟',
                'a' => '۱۸', 'b' => '۲۴', 'c' => '۳۲', 'd' => '۲۰',
                'correct' => 'c',
                'exp' => 'هر جمله دو برابر قبلی است → ۳۲.',
                'diff' => 'easy', 'year' => '1400',
            ],
            [
                'subject' => 'iq',
                'q' => 'اگر همه گل‌ها درخت باشند و بعضی درخت‌ها میوه بدهند، کدام درست است؟',
                'a' => 'حتماً همه گل‌ها میوه می‌دهند', 'b' => 'ممکن است بعضی گل‌ها میوه بدهند', 'c' => 'هیچ گلی میوه نمی‌دهد', 'd' => 'گل همان میوه است',
                'correct' => 'b',
                'exp' => 'از مقدمات فقط امکانِ اشتراک نتیجه می‌شود، نه وجوب برای همه.',
                'diff' => 'hard', 'year' => '1403',
            ],
            [
                'subject' => 'general',
                'q' => 'پایتخت ایران کدام شهر است؟',
                'a' => 'اصفهان', 'b' => 'مشهد', 'c' => 'تهران', 'd' => 'شیراز',
                'correct' => 'c',
                'exp' => 'تهران پایتخت رسمی جمهوری اسلامی ایران است.',
                'diff' => 'easy', 'year' => '1400',
            ],
            [
                'subject' => 'general',
                'q' => 'نهاد برگزارکننده آزمون‌های سراسری عمدتاً کدام است؟',
                'a' => 'شهرداری', 'b' => 'سازمان سنجش', 'c' => 'بانک مرکزی', 'd' => 'ثبت احوال',
                'correct' => 'b',
                'exp' => 'سازمان سنجش آموزش کشور متولی بسیاری از آزمون‌های سراسری است.',
                'diff' => 'easy', 'year' => '1402',
            ],
            [
                'subject' => 'physics',
                'q' => 'واحد اندازه‌گیری نیرو در SI کدام است؟',
                'a' => 'ژول', 'b' => 'وات', 'c' => 'نیوتن', 'd' => 'پاسکال',
                'correct' => 'c',
                'exp' => 'نیرو با نیوتن (N) سنجیده می‌شود.',
                'diff' => 'easy', 'year' => '1401',
            ],
            [
                'subject' => 'chemistry',
                'q' => 'نماد شیمیایی آب کدام است؟',
                'a' => 'CO2', 'b' => 'H2O', 'c' => 'O2', 'd' => 'NaCl',
                'correct' => 'b',
                'exp' => 'مولکول آب H₂O است.',
                'diff' => 'easy', 'year' => '1400',
            ],
        ];

        $created = 0;
        foreach ($bank as $item) {
            $exists = Question::query()
                ->where('exam_id', $exam->id)
                ->where('question_text', $item['q'])
                ->exists();
            if ($exists) {
                continue;
            }

            Question::query()->create([
                'exam_id' => $exam->id,
                'question_text' => $item['q'],
                'question_type' => 'multiple_choice',
                'option_a' => $item['a'],
                'option_b' => $item['b'],
                'option_c' => $item['c'],
                'option_d' => $item['d'],
                'correct_answer' => $item['correct'],
                'explanation' => $item['exp']."\n\nسال {$item['year']} — منبع: نمونه تألیفی جاب‌آزمون",
                'difficulty' => $item['diff'],
                'subject' => $item['subject'],
                'source' => 'نمونه تألیفی جاب‌آزمون',
                'exam_year' => $item['year'],
            ]);
            $created++;
        }

        $exam->update([
            'total_questions' => Question::query()->where('exam_id', $exam->id)->count(),
        ]);

        if ($this->command) {
            $this->command->info("PracticeQuestionsSeeder: +{$created} questions on exam #{$exam->id} ({$exam->title})");
        }
    }
}
