<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserLoginSession;
use Illuminate\Support\Facades\Request;
use Morilog\Jalali\Jalalian;

class LoginSessionService
{
    public function start(User $user, ?int $tokenId = null, string $source = 'api'): UserLoginSession
    {
        return UserLoginSession::query()->create([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'logged_in_at' => now(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'source' => $source,
        ]);
    }

    public function end(User $user, ?int $tokenId = null): ?UserLoginSession
    {
        $query = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->latest('logged_in_at');

        if ($tokenId !== null) {
            $session = (clone $query)->where('token_id', $tokenId)->first()
                ?? $query->first();
        } else {
            $session = $query->first();
        }

        if (! $session) {
            return null;
        }

        $out = now();
        $session->update([
            'logged_out_at' => $out,
            'duration_seconds' => max(0, (int) $session->logged_in_at->diffInSeconds($out)),
        ]);

        return $session->fresh();
    }

    /**
     * @return array{sessions: list<array<string, mixed>>, monthly: list<array<string, mixed>>}
     */
    public function reportForUser(User $user, int $sessionLimit = 50): array
    {
        $sessions = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->latest('logged_in_at')
            ->limit($sessionLimit)
            ->get();

        $sessionItems = $sessions->map(fn (UserLoginSession $row) => $this->mapSession($row))->all();

        return [
            'sessions' => $sessionItems,
            'monthly' => $this->monthlySummaries($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function monthlySummaries(User $user): array
    {
        $all = UserLoginSession::query()
            ->where('user_id', $user->id)
            ->orderBy('logged_in_at')
            ->get();

        $nowJ = Jalalian::now();
        $currentKey = sprintf('%04d-%02d', $nowJ->getYear(), $nowJ->getMonth());
        $months = [];

        foreach ($all as $row) {
            $j = Jalalian::fromDateTime($row->logged_in_at);
            $key = sprintf('%04d-%02d', $j->getYear(), $j->getMonth());
            if ($key >= $currentKey) {
                // فقط ماه‌های تمام‌شده
                continue;
            }

            if (! isset($months[$key])) {
                $months[$key] = [
                    'year' => $j->getYear(),
                    'month' => $j->getMonth(),
                    'month_name' => $j->format('%B'),
                    'label' => $j->format('%B').' '.$j->getYear(),
                    'sessions_count' => 0,
                    'total_duration_seconds' => 0,
                ];
            }

            $months[$key]['sessions_count']++;
            $months[$key]['total_duration_seconds'] += $row->effectiveDurationSeconds();
        }

        krsort($months);

        return array_values($months);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapSession(UserLoginSession $row): array
    {
        $duration = $row->effectiveDurationSeconds();
        $inJ = Jalalian::fromDateTime($row->logged_in_at);
        $outJ = $row->logged_out_at ? Jalalian::fromDateTime($row->logged_out_at) : null;

        return [
            'id' => $row->id,
            'logged_in_at' => $row->logged_in_at?->toIso8601String(),
            'logged_out_at' => $row->logged_out_at?->toIso8601String(),
            'logged_in_label' => $inJ->format('Y/m/d H:i'),
            'logged_out_label' => $outJ?->format('Y/m/d H:i'),
            'duration_seconds' => $duration,
            'duration_label' => $this->formatDuration($duration),
            'is_active' => $row->isOpen(),
            'ip_address' => $row->ip_address,
            'source' => $row->source,
        ];
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' ثانیه';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' ساعت';
        }
        if ($minutes > 0) {
            $parts[] = $minutes.' دقیقه';
        }
        if ($parts === []) {
            $parts[] = 'کمتر از یک دقیقه';
        }

        return implode(' و ', $parts);
    }
}
