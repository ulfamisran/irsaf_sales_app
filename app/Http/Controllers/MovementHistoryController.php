<?php

namespace App\Http\Controllers;

use App\Services\MovementHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovementHistoryController extends Controller
{
    public function __construct(
        protected MovementHistoryService $history
    ) {}

    public function index(Request $request): View
    {
        $mode = $request->input('mode') === 'sku' ? 'sku' : 'serial';
        $sort = $request->input('sort') === 'created_at' ? 'created_at' : 'transaction';
        $q = trim((string) $request->input('q', ''));
        $result = $q !== ''
            ? ($mode === 'sku' ? $this->history->forSku($q, $sort) : $this->history->forSerial($q, $sort))
            : null;

        return view('stock-units.movement', [
            'mode' => $mode,
            'sort' => $sort,
            'q' => $q,
            'result' => $result,
        ]);
    }
}
