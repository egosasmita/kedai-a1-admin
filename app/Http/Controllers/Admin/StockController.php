<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'condition' => ['nullable', Rule::in(['safe', 'low', 'empty'])],
            'history' => ['nullable', Rule::in(['all', 'in', 'out'])],
        ]);

        $itemQuery = DB::table('stock_items')->where('is_active', true);
        $items = (clone $itemQuery)
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($filters['condition'] ?? null, function (Builder $query, string $condition): void {
                match ($condition) {
                    'empty' => $query->where('quantity', '<=', 0),
                    'low' => $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'minimum_quantity'),
                    'safe' => $query->whereColumn('quantity', '>', 'minimum_quantity'),
                };
            })
            ->orderByRaw('CASE WHEN quantity <= 0 THEN 0 WHEN quantity <= minimum_quantity THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        $transactions = DB::table('stock_transactions')
            ->join('stock_items', 'stock_items.id', '=', 'stock_transactions.stock_item_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transactions.recorded_by')
            ->when(
                isset($filters['history']) && $filters['history'] !== 'all',
                fn (Builder $query) => $query->where('stock_transactions.type', $filters['history']),
            )
            ->select([
                'stock_transactions.*',
                'stock_items.name as item_name',
                'stock_items.unit',
                'users.name as recorder_name',
            ])
            ->latest('stock_transactions.occurred_at')
            ->limit(8)
            ->get();

        return view('admin.stock.index', [
            'items' => $items,
            'transactions' => $transactions,
            'itemCount' => (clone $itemQuery)->count(),
            'lowStockCount' => (clone $itemQuery)->whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            'safeStockCount' => (clone $itemQuery)->whereColumn('quantity', '>', 'minimum_quantity')->count(),
            'stockValue' => (clone $itemQuery)->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total'),
            'stockOptions' => (clone $itemQuery)->orderBy('name')->get(['id', 'name', 'unit', 'quantity']),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160', 'unique:stock_items,name'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:stock_items,sku'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'minimum_quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $itemId = DB::table('stock_items')->insertGetId([
                'name' => trim($validated['name']),
                'sku' => filled($validated['sku'] ?? null) ? trim($validated['sku']) : null,
                'unit' => trim($validated['unit']),
                'quantity' => $validated['quantity'],
                'minimum_quantity' => $validated['minimum_quantity'],
                'unit_cost' => $validated['unit_cost'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ((float) $validated['quantity'] > 0) {
                DB::table('stock_transactions')->insert([
                    'stock_item_id' => $itemId,
                    'recorded_by' => $request->user()->id,
                    'type' => 'in',
                    'quantity' => $validated['quantity'],
                    'unit_cost' => $validated['unit_cost'],
                    'quantity_before' => 0,
                    'quantity_after' => $validated['quantity'],
                    'reference' => 'Stok awal',
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('admin.stock.index')->with('status', 'Bahan baku berhasil ditambahkan.');
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            'quantity' => [
                'required',
                'numeric',
                $request->input('type') === 'adjustment' ? 'min:0' : 'min:0.001',
                'max:999999999',
            ],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $item = DB::table('stock_items')
                ->where('id', $validated['stock_item_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                abort(404);
            }

            $before = (float) $item->quantity;
            $amount = (float) $validated['quantity'];
            $after = match ($validated['type']) {
                'in' => $before + $amount,
                'out' => $before - $amount,
                'adjustment' => $amount,
            };

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Jumlah keluar melebihi stok yang tersedia.',
                ]);
            }

            DB::table('stock_items')->where('id', $item->id)->update([
                'quantity' => $after,
                'unit_cost' => $validated['unit_cost'] ?? $item->unit_cost,
                'updated_at' => now(),
            ]);
            DB::table('stock_transactions')->insert([
                'stock_item_id' => $item->id,
                'recorded_by' => $request->user()->id,
                'type' => $validated['type'],
                'quantity' => $validated['type'] === 'adjustment' ? abs($after - $before) : $amount,
                'unit_cost' => $validated['unit_cost'] ?? null,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reference' => filled($validated['reference'] ?? null) ? trim($validated['reference']) : null,
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.stock.index')->with('status', 'Transaksi stok berhasil dicatat.');
    }
}
