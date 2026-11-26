<?php

namespace App\Http\Controllers;

use App\Models\AuthorizationRequest;
use App\Models\Branch;
use App\Models\Distribution;
use App\Models\Purchase;
use App\Models\Rental;
use App\Models\Sale;
use App\Models\Service;
use App\Models\UnitExchange;
use App\Models\Warehouse;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AuthorizationController extends Controller
{
    public function __construct(private AuthorizationService $authorizations) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $this->authorizations->canOpenQueue($user), 403);

        $allowedModules = $this->authorizations->assignedModules($user);
        abort_unless($allowedModules !== [], 403);

        $tab = $request->query('tab') === 'riwayat' ? 'riwayat' : 'siap';
        $module = (string) $request->query('module', $allowedModules[0] ?? 'sales');
        if (! isset(AuthorizationService::MODULES[$module]) || ! in_array($module, $allowedModules, true)) {
            $module = $allowedModules[0];
        }
        $request->merge(['module' => $module]);

        $canFilterLocation = $user->isSuperAdminOrAdminPusat();
        $query = $this->authorizations->listQuery($user, $request, $tab);

        return view('authorizations.index', [
            'tab' => $tab,
            'module' => $module,
            'modules' => AuthorizationService::MODULES,
            'allowedModules' => $allowedModules,
            'moduleLabel' => AuthorizationService::MODULES[$module]['label'] ?? $module,
            'rows' => $tab === 'siap' ? $query->latest('id')->paginate(20)->withQueryString() : null,
            'history' => $tab === 'riwayat' ? $query->latest('id')->paginate(20)->withQueryString() : null,
            'summary' => $tab === 'siap'
                ? $this->authorizations->pendingCounts($user, $request)
                : $this->authorizations->historyCounts($user, $request),
            'pendingTotal' => $this->authorizations->pendingFor($user)->count(),
            'canFilterLocation' => $canFilterLocation,
            'branches' => $canFilterLocation ? Branch::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'warehouses' => $canFilterLocation ? Warehouse::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function pendingFeed(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->authorizations->canOpenQueue($user), 403);

        $latest = $this->authorizations->pendingFor($user)
            ->with('requester')
            ->latest('id')
            ->first();

        if (! $latest) {
            return response()->json([
                'count' => 0,
                'latest_id' => 0,
                'item' => null,
            ]);
        }

        $moduleLabel = AuthorizationService::MODULES[$latest->module]['label'] ?? $latest->module;

        return response()->json([
            'count' => $this->authorizations->pendingFor($user)->count(),
            'latest_id' => (int) $latest->id,
            'item' => [
                'id' => (int) $latest->id,
                'module' => $latest->module,
                'module_label' => $moduleLabel,
                'document_number' => $latest->document_number,
                'requester' => $latest->requester?->name,
                'url' => route('authorizations.index', [
                    'tab' => 'siap',
                    'module' => $latest->module,
                ]),
            ],
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string'],
            'document_id' => ['required', 'integer'],
        ]);

        try {
            $model = $this->document($validated['module'], (int) $validated['document_id']);
            $this->authorizations->submit($model, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Dokumen diajukan untuk otorisasi.'));
    }

    public function show(Request $request, AuthorizationRequest $authorizationRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->authorizations->canOpenQueue($user), 403);
        abort_unless($authorizationRequest->isPending(), 404);

        try {
            // Reuse the same access rules as approve/reject (module + lokasi).
            $this->authorizations->assertCanView($authorizationRequest, $user);
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        return response()->json($this->authorizations->documentDetail($authorizationRequest));
    }

    public function approve(Request $request, AuthorizationRequest $authorizationRequest): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->authorizations->approve(
                $authorizationRequest,
                $request->user(),
                $validated['notes'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Otorisasi disetujui. Dokumen siap di-release.'));
    }

    public function approveMany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $saved = $this->authorizations->approveMany($request->user(), $validated['ids']);

        return back()->with('success', __('Otorisasi disetujui: :count dokumen.', ['count' => $saved]));
    }

    public function reject(Request $request, AuthorizationRequest $authorizationRequest): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->authorizations->reject($authorizationRequest, $request->user(), $validated['notes']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Otorisasi ditolak dan dokumen dibatalkan.'));
    }

    private function document(string $module, int $id): Sale|Purchase|Service|Rental|UnitExchange|Distribution
    {
        $class = AuthorizationService::MODULES[$module]['model'] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException(__('Modul otorisasi tidak dikenal.'));
        }

        $model = $class::query()->find($id);
        if (! $model) {
            throw new InvalidArgumentException(__('Dokumen tidak ditemukan.'));
        }

        return $model;
    }
}
