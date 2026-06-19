<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSarMoney;
use App\Models\AuditLog;
use App\Models\CostItem;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Service;
use App\Models\ServiceCostItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use HandlesSarMoney;

    public function index(): RedirectResponse
    {
        return redirect('/app/expenses/records');
    }

    public function records(): Response
    {
        return Inertia::render('Expenses/Records', [
            'categories' => $this->categoryRows(),
            'expenses' => $this->expenseRows(),
            'summary' => $this->summary(),
            'expenseStatuses' => $this->expenseStatuses(),
        ]);
    }

    public function categories(): Response
    {
        return Inertia::render('Expenses/Categories', [
            'categories' => $this->categoryRows(),
            'summary' => $this->summary(),
            'expenseTypes' => $this->expenseTypes(),
        ]);
    }

    public function costItems(): Response
    {
        return Inertia::render('Expenses/CostItems', [
            'costItems' => $this->costItemRows(),
            'summary' => $this->summary(),
            'itemTypes' => $this->itemTypes(),
        ]);
    }

    public function serviceCosts(): Response
    {
        return Inertia::render('Expenses/ServiceCosts', [
            'costItems' => $this->costItemRows(),
            'serviceOptions' => $this->serviceOptions(),
            'serviceCostBreakdowns' => $this->serviceCostBreakdowns(),
            'itemTypes' => $this->itemTypes(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:80', 'unique:expense_categories,code'],
            'expense_type' => ['required', Rule::in(array_column($this->expenseTypes(), 'key'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category = ExpenseCategory::create([
            ...$validated,
            'code' => $validated['code'] ? Str::slug($validated['code'], '_') : Str::slug($validated['name'], '_'),
        ]);

        $this->audit($request, 'expense_category.created', $category, [], $category->only(['name', 'code', 'expense_type', 'is_active']));

        return back()->with('success', __('Expense type created.'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'amount_sar', 'amount_halalas');
        $this->mergeSarFromHalalas($request, 'vat_sar', 'vat_halalas');

        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'vendor' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_sar' => $this->positiveSarMoneyRules(),
            'vat_sar' => $this->sarMoneyRules(),
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(array_column($this->expenseStatuses(), 'key'))],
            'receipt_path' => ['nullable', 'string', 'max:255'],
        ]);

        $category = ExpenseCategory::query()->findOrFail($validated['expense_category_id']);
        $expense = Expense::create([
            ...collect($validated)
                ->except(['amount_sar', 'vat_sar'])
                ->all(),
            'expense_type' => $category->expense_type,
            'category' => $category->code,
            'amount_halalas' => $this->sarToHalalas($validated['amount_sar']),
            'vat_halalas' => $this->sarToHalalas($validated['vat_sar']),
        ]);

        $this->audit($request, 'expense.created', $expense, [], $expense->only([
            'expense_category_id',
            'expense_date',
            'expense_type',
            'category',
            'vendor',
            'amount_halalas',
            'vat_halalas',
            'payment_method',
            'status',
        ]));

        return back()->with('success', __('Expense recorded.'));
    }

    public function storeCostItem(Request $request): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'purchase_cost_sar', 'purchase_cost_halalas');
        $this->mergeSarFromHalalas($request, 'default_cost_per_use_sar', 'default_cost_per_use_halalas');

        $request->merge([
            'purchase_cost_sar' => $request->input('purchase_cost_sar', '0.00'),
            'default_cost_per_use_sar' => $request->input('default_cost_per_use_sar', null),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'item_type' => ['required', Rule::in(array_column($this->itemTypes(), 'key'))],
            'unit' => ['required', 'string', 'max:50'],
            'purchase_cost_sar' => $this->sarMoneyRules(),
            'estimated_life_months' => ['required', 'integer', 'min:1', 'max:240'],
            'estimated_monthly_uses' => ['required', 'integer', 'min:1', 'max:999999'],
            'default_cost_per_use_sar' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $purchaseCost = $this->sarToHalalas($validated['purchase_cost_sar']);
        $defaultCost = filled($validated['default_cost_per_use_sar'])
            ? $this->sarToHalalas($validated['default_cost_per_use_sar'])
            : (int) round($purchaseCost / max(1, ((int) $validated['estimated_life_months']) * ((int) $validated['estimated_monthly_uses'])));

        $item = CostItem::create([
            ...collect($validated)
                ->except(['purchase_cost_sar', 'default_cost_per_use_sar'])
                ->all(),
            'purchase_cost_halalas' => $purchaseCost,
            'default_cost_per_use_halalas' => $defaultCost,
        ]);

        $this->audit($request, 'cost_item.created', $item, [], $item->only(['name', 'item_type', 'purchase_cost_halalas', 'default_cost_per_use_halalas']));

        return back()->with('success', __('Cost item created.'));
    }

    public function storeServiceCostItem(Request $request): RedirectResponse
    {
        $this->mergeSarFromHalalas($request, 'cost_per_use_sar', 'cost_per_use_halalas');

        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'cost_item_id' => ['required', 'integer', 'exists:cost_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'cost_per_use_sar' => $this->sarMoneyRules(),
            'charge_customer' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $lineTotal = (int) round($this->sarToHalalas($validated['cost_per_use_sar']) * (float) $validated['quantity']);
        $serviceCostItem = ServiceCostItem::updateOrCreate([
            'service_id' => $validated['service_id'],
            'cost_item_id' => $validated['cost_item_id'],
        ], [
            'quantity' => $validated['quantity'],
            'cost_per_use_halalas' => $this->sarToHalalas($validated['cost_per_use_sar']),
            'line_total_halalas' => $lineTotal,
            'charge_customer' => $validated['charge_customer'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->audit($request, 'service_cost_item.synced', $serviceCostItem, [], $serviceCostItem->only([
            'service_id',
            'cost_item_id',
            'quantity',
            'cost_per_use_halalas',
            'line_total_halalas',
            'charge_customer',
        ]));

        return back()->with('success', __('Service cost item linked.'));
    }

    private function categoryRows(): array
    {
        return ExpenseCategory::query()
            ->withCount('expenses')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'expense_type' => $category->expense_type,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'expenses_count' => $category->expenses_count,
            ])
            ->values()
            ->all();
    }

    private function expenseRows(): array
    {
        return Expense::query()
            ->with('categoryModel')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date ? substr((string) $expense->expense_date, 0, 10) : null,
                'expense_category_id' => $expense->expense_category_id,
                'expense_type' => $expense->expense_type,
                'category' => $expense->category,
                'category_name' => $expense->categoryModel?->name,
                'vendor' => $expense->vendor,
                'description' => $expense->description,
                'amount_halalas' => $expense->amount_halalas,
                'amount_sar' => $this->halalasToSarString($expense->amount_halalas),
                'vat_halalas' => $expense->vat_halalas,
                'vat_sar' => $this->halalasToSarString($expense->vat_halalas),
                'payment_method' => $expense->payment_method,
                'payment_reference' => $expense->payment_reference,
                'status' => $expense->status,
                'receipt_path' => $expense->receipt_path,
            ])
            ->values()
            ->all();
    }

    private function summary(): array
    {
        $reportableStatuses = ['approved', 'paid'];

        return [
            'direct_cost_halalas' => (int) Expense::query()
                ->where('expense_type', 'direct_cost')
                ->whereIn('status', $reportableStatuses)
                ->sum('amount_halalas'),
            'operating_expenses_halalas' => (int) Expense::query()
                ->where('expense_type', 'operating_expense')
                ->whereIn('status', $reportableStatuses)
                ->sum('amount_halalas'),
            'vat_halalas' => (int) Expense::query()
                ->whereIn('status', $reportableStatuses)
                ->sum('vat_halalas'),
            'records_count' => Expense::query()->count(),
            'cost_items_count' => CostItem::query()->count(),
        ];
    }

    private function costItemRows(): array
    {
        return CostItem::query()
            ->withCount('serviceCostItems')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (CostItem $item): array => $this->serializeCostItem($item))
            ->values()
            ->all();
    }

    private function serializeCostItem(CostItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'item_type' => $item->item_type,
            'unit' => $item->unit,
            'purchase_cost_halalas' => $item->purchase_cost_halalas,
            'purchase_cost_sar' => $this->halalasToSarString($item->purchase_cost_halalas),
            'estimated_life_months' => $item->estimated_life_months,
            'estimated_monthly_uses' => $item->estimated_monthly_uses,
            'default_cost_per_use_halalas' => $item->default_cost_per_use_halalas,
            'default_cost_per_use_sar' => $this->halalasToSarString($item->default_cost_per_use_halalas),
            'notes' => $item->notes,
            'is_active' => $item->is_active,
            'service_cost_items_count' => $item->service_cost_items_count ?? $item->serviceCostItems()->count(),
        ];
    }

    private function serviceOptions(): array
    {
        return Service::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category,
                'base_price_halalas' => $service->base_price_halalas,
                'base_price_sar' => $this->halalasToSarString($service->base_price_halalas),
            ])
            ->values()
            ->all();
    }

    private function serviceCostBreakdowns(): array
    {
        return Service::query()
            ->with(['serviceCostItems.costItem'])
            ->whereHas('serviceCostItems')
            ->orderBy('title')
            ->get()
            ->map(fn (Service $service): array => $this->serviceCostBreakdown($service))
            ->values()
            ->all();
    }

    public function serviceCostBreakdown(Service $service): array
    {
        $service->loadMissing('serviceCostItems.costItem');
        $items = $service->serviceCostItems
            ->map(fn (ServiceCostItem $row): array => [
                'id' => $row->id,
                'cost_item_id' => $row->cost_item_id,
                'name' => $row->costItem?->name,
                'item_type' => $row->costItem?->item_type,
                'unit' => $row->costItem?->unit,
                'quantity' => $row->quantity,
                'cost_per_use_halalas' => $row->cost_per_use_halalas,
                'cost_per_use_sar' => $this->halalasToSarString($row->cost_per_use_halalas),
                'line_total_halalas' => $row->line_total_halalas,
                'line_total_sar' => $this->halalasToSarString($row->line_total_halalas),
                'charge_customer' => $row->charge_customer,
                'notes' => $row->notes,
            ])
            ->values();
        $total = (int) $service->serviceCostItems->sum('line_total_halalas');

        return [
            'service' => [
                'id' => $service->id,
                'title' => $service->title,
                'category' => $service->category,
                'base_price_halalas' => $service->base_price_halalas,
                'base_price_sar' => $this->halalasToSarString($service->base_price_halalas),
            ],
            'items' => $items,
            'total_cost_halalas' => $total,
            'total_cost_sar' => $this->halalasToSarString($total),
            'gross_profit_estimate_halalas' => $service->base_price_halalas - $total,
            'gross_profit_estimate_sar' => $this->halalasToSarString($service->base_price_halalas - $total),
        ];
    }

    private function expenseTypes(): array
    {
        return [
            ['key' => 'direct_cost', 'label' => 'Direct job cost'],
            ['key' => 'operating_expense', 'label' => 'Operating expense'],
        ];
    }

    private function expenseStatuses(): array
    {
        return [
            ['key' => 'draft', 'label' => 'Draft'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'paid', 'label' => 'Paid'],
            ['key' => 'rejected', 'label' => 'Rejected'],
        ];
    }

    private function itemTypes(): array
    {
        return [
            ['key' => 'equipment', 'label' => 'Equipment'],
            ['key' => 'material', 'label' => 'Material'],
            ['key' => 'tool', 'label' => 'Tool'],
            ['key' => 'vehicle', 'label' => 'Vehicle'],
            ['key' => 'supply', 'label' => 'Supply'],
        ];
    }

    private function audit(Request $request, string $action, Model $model, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'changes' => [
                'updated' => [
                    'old' => $old,
                    'new' => $new,
                ],
            ],
        ]);
    }
}
