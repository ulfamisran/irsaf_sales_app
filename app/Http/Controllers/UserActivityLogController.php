<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\CatatRange;
use App\Support\ModuleAccessCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UserActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeViewer();

        $today = Carbon::now(CatatRange::TZ)->toDateString();
        $fromInput = $request->string('date_from')->toString();
        $toInput = $request->string('date_to')->toString();
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput) ? $fromInput : $today;
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput) ? $toInput : $today;

        $from = Carbon::parse($dateFrom, CatatRange::TZ)->startOfDay();
        $to = Carbon::parse($dateTo, CatatRange::TZ)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $to = $from->copy()->addDays(366);
        }

        $totalDays = (int) $from->diffInDays($to) + 1;
        $page = min($totalDays, max(1, $request->integer('day', 1)));
        $day = $from->copy()->addDays($page - 1);
        $date = $day->toDateString();

        $logs = UserActivityLog::query()
            ->with('user:id,name')
            ->whereBetween('occurred_at', [
                $day->copy()->startOfDay()->utc(),
                $day->copy()->endOfDay()->utc(),
            ])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')->toString()))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $groups = $logs->groupBy(
            fn (UserActivityLog $log) => $log->occurred_at?->timezone(CatatRange::TZ)->format('H:00') ?? '-'
        );

        return view('activity-logs.index', [
            'date' => $date,
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'page' => $page,
            'totalDays' => $totalDays,
            'groups' => $groups,
            'total' => $logs->count(),
            'truncated' => $logs->count() === 1000,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => $this->actions(),
            'modules' => $this->modules(),
        ]);
    }

    private function authorizeViewer(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && $user->isStrictSuperAdmin(),
            403,
            __('Hanya Super Admin yang dapat melihat log aktivitas.')
        );
    }

    /**
     * @return array<string, string>
     */
    private function actions(): array
    {
        return [
            UserActivityLog::LOGIN => 'Masuk',
            UserActivityLog::LOGIN_FAILED => 'Gagal masuk',
            UserActivityLog::LOGOUT => 'Keluar',
            UserActivityLog::CREATE => 'Tambah',
            UserActivityLog::UPDATE => 'Ubah',
            UserActivityLog::DELETE => 'Hapus',
            UserActivityLog::CANCEL => 'Batal',
            UserActivityLog::ACTIVATE => 'Aktifkan',
            UserActivityLog::DEACTIVATE => 'Nonaktifkan',
        ];
    }

    /**
     * @return Collection<string, string>
     */
    private function modules(): Collection
    {
        return collect(ModuleAccessCatalog::MODULES)
            ->mapWithKeys(fn (array $module) => [$module['slug'] => $module['name']])
            ->put('auth', 'Akun')
            ->put('profile', 'Profil')
            ->put('role-access', 'Akses Modul')
            ->put('verification-cutoff', 'Cut-off Verifikasi')
            ->put('unit-exchanges', 'Tukar Unit')
            ->sort();
    }
}
