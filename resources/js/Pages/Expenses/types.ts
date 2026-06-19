export type Option = {
    key: string;
    label: string;
};

export type ExpenseCategory = {
    id: number;
    name: string;
    code: string;
    expense_type: string;
    description?: string | null;
    is_active: boolean;
    expenses_count: number;
};

export type ExpenseRow = {
    id: number;
    expense_date?: string | null;
    expense_category_id?: number | null;
    expense_type: string;
    category: string;
    category_name?: string | null;
    vendor?: string | null;
    description?: string | null;
    amount_halalas: number;
    amount_sar: string;
    vat_halalas: number;
    vat_sar: string;
    payment_method: string;
    payment_reference?: string | null;
    status: string;
    receipt_path?: string | null;
};

export type ExpenseSummary = {
    direct_cost_halalas: number;
    operating_expenses_halalas: number;
    vat_halalas: number;
    records_count: number;
    cost_items_count: number;
};

export type CostItem = {
    id: number;
    name: string;
    item_type: string;
    unit: string;
    purchase_cost_halalas: number;
    purchase_cost_sar: string;
    estimated_life_months: number;
    estimated_monthly_uses: number;
    default_cost_per_use_halalas: number;
    default_cost_per_use_sar: string;
    notes?: string | null;
    is_active: boolean;
    service_cost_items_count: number;
};

export type ServiceOption = {
    id: number;
    title: string;
    category: string;
    base_price_halalas: number;
    base_price_sar: string;
};

export type ServiceCostBreakdownItem = {
    id: number;
    cost_item_id: number;
    name?: string | null;
    item_type?: string | null;
    unit?: string | null;
    quantity: string | number;
    cost_per_use_halalas: number;
    cost_per_use_sar: string;
    line_total_halalas: number;
    line_total_sar: string;
    charge_customer: boolean;
    notes?: string | null;
};

export type ServiceCostBreakdown = {
    service: ServiceOption;
    items: ServiceCostBreakdownItem[];
    total_cost_halalas: number;
    total_cost_sar: string;
    gross_profit_estimate_halalas: number;
    gross_profit_estimate_sar: string;
};
