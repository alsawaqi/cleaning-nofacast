export type Money = number;

export type User = {
    id: number;
    name: string;
    email: string;
    role: string;
    locale: string;
    permissions: string[];
};

export type AppSharedProps = {
    auth?: {
        user?: User | null;
        roles?: Record<string, string>;
    };
    flash?: {
        success?: string | null;
    };
    app?: {
        name: string;
        locale: string;
        dir: 'rtl' | 'ltr';
        supportedLocales: string[];
    };
    i18n?: {
        locale: string;
        dir: 'rtl' | 'ltr';
        messages: Record<string, unknown>;
    };
    notifications?: {
        total?: number;
        pendingBookingRequests?: number;
        pendingServiceRequests?: number;
        pendingPaymentProofs?: number;
        pendingContractDecisions?: number;
        pendingVisitReviews?: number;
        pendingWorkerAvailabilityConflicts?: number;
        items?: Array<{
            id?: number;
            visit_id?: number;
            type: string;
            title_key?: string;
            priority?: string;
            body?: string | null;
            href?: string;
            scheduled_for?: string | null;
            requested_for?: string | null;
            paid_on?: string | null;
            created_at?: string | null;
            starts_at?: string | null;
            ends_at?: string | null;
        }>;
        bookingRequests?: Array<{
            id: number;
            customer?: string | null;
            site?: string | null;
            service?: string | null;
            package?: string | null;
            requested_for?: string | null;
            starts_at?: string | null;
            href: string;
        }>;
        serviceRequests?: Array<{
            id: number;
            type: string;
            status: string;
            priority: string;
            customer?: string | null;
            contract?: string | null;
            site?: string | null;
            service?: string | null;
            requested_for?: string | null;
            starts_at?: string | null;
            href: string;
        }>;
        paymentProofs?: Array<{
            id: number;
            customer?: string | null;
            invoice?: string | null;
            amount_halalas: number;
            paid_on?: string | null;
            href: string;
        }>;
        contractDecisions?: Array<{
            id: number;
            decision: string;
            status: string;
            customer?: string | null;
            contract?: string | null;
            site?: string | null;
            service?: string | null;
            created_at?: string | null;
            href: string;
        }>;
        visitReviews?: Array<{
            visit_id: number;
            contract?: string | null;
            customer?: string | null;
            site?: string | null;
            service?: string | null;
            worker?: string | null;
            scheduled_for?: string | null;
            starts_at?: string | null;
            ends_at?: string | null;
            href: string;
        }>;
        workerAvailabilityConflicts?: Array<{
            visit_id: number;
            worker_id?: number | null;
            worker?: string | null;
            contract?: string | null;
            site?: string | null;
            service?: string | null;
            scheduled_for?: string | null;
            starts_at?: string | null;
            ends_at?: string | null;
            block_status?: string | null;
            block_reason?: string | null;
            href: string;
        }>;
    };
};

export type Service = {
    id: number;
    title: string;
    slug: string;
    category: string;
    description?: string | null;
    pricing_type: string;
    base_price_halalas: Money;
    base_price_sar?: string;
    vat_rate: number;
    prices_include_vat: boolean;
    materials_included: boolean;
    minimum_billable_minutes: number;
    default_workers: number;
    default_duration_minutes: number;
    default_material_cost_halalas?: Money;
    default_material_cost_sar?: string;
    material_policy: string;
    included_materials: string[];
    extra_hour_rate_halalas?: Money;
    extra_hour_rate_sar?: string;
    overtime_policy: string;
    allowed_frequencies: string[];
    required_certificates: string[];
    checklist_template: Array<{ label: string; is_required?: boolean }>;
    sla_kpi_template: SlaKpiTemplateItem[];
    is_active: boolean;
    edit_url?: string;
    packages?: ServicePackage[];
    pricing_rules?: ServicePricingRule[];
    cost_items?: ServiceCostItem[];
    cost_breakdown?: ServiceCostBreakdown;
};

export type ServiceCostItem = {
    id: number;
    cost_item_id: number;
    name?: string | null;
    item_type?: string | null;
    unit?: string | null;
    quantity: string | number;
    cost_per_use_halalas: Money;
    cost_per_use_sar?: string;
    line_total_halalas: Money;
    line_total_sar?: string;
    charge_customer: boolean;
    notes?: string | null;
};

export type ServiceCostBreakdown = {
    total_cost_halalas: Money;
    total_cost_sar?: string;
    gross_profit_estimate_halalas: Money;
    gross_profit_estimate_sar?: string;
    items_count: number;
};

export type ServicePackage = {
    id: number;
    name: string;
    description?: string | null;
    billing_cycle: string;
    visit_frequency: string;
    visits_per_week?: number | null;
    hours_per_visit?: string | number | null;
    worker_count: number;
    duration_minutes: number;
    expected_labor_minutes?: number;
    material_cost_halalas?: Money;
    material_cost_sar?: string;
    price_halalas: Money;
    price_sar?: string;
    vat_rate: number;
    prices_include_vat: boolean;
    checklist_template: Array<{ label: string; is_required?: boolean }>;
    sla_kpi_template: SlaKpiTemplateItem[];
    is_active: boolean;
};

export type SlaKpiTemplateItem = {
    code: string;
    label: string;
    target: number;
    unit: string;
    weight: number;
    direction: 'at_least' | 'at_most' | string;
};

export type ServicePricingRule = {
    id: number;
    name: string;
    pricing_type: string;
    unit_label: string;
    unit_price_halalas: Money;
    unit_price_sar?: string;
    minimum_quantity: number;
    maximum_quantity?: number | null;
    vat_rate: number;
    prices_include_vat: boolean;
    applies_to: string[];
    is_active: boolean;
};

export type Customer = {
    id: number;
    name: string;
    customer_type: string;
    phone?: string | null;
    email?: string | null;
    preferred_channel: string;
    preferred_locale: string;
    vat_number?: string | null;
    status: string;
    edit_url?: string;
    detail_url?: string;
    sites_count?: number;
    contracts_count?: number;
    invoices_count?: number;
    sites?: CustomerSite[];
};

export type CustomerSite = {
    id: number;
    country_code?: string | null;
    name: string;
    city: string;
    district?: string | null;
    address?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    google_place_id?: string | null;
    formatted_address?: string | null;
    contact_name?: string | null;
    contact_phone?: string | null;
};

export type Worker = {
    id: number;
    user_id?: number | null;
    user?: {
        id: number;
        name: string;
        email: string;
    } | null;
    employee_code: string;
    name: string;
    phone?: string | null;
    hired_on?: string | null;
    nationality?: string | null;
    role_language: string;
    job_role: string;
    status: string;
    cost_rate_halalas: Money;
    cost_rate_sar?: string;
    skills: string[];
    certifications: string[];
    availability_notes?: string | null;
    detail_url?: string;
    edit_url?: string;
    assignments_count?: number;
    visits_count?: number;
    documents: WorkerDocument[];
    training_records: TrainingRecord[];
    compliance: {
        documents: number;
        expired_documents: number;
        expiring_documents: number;
        training_records: number;
        expired_training: number;
        expiring_training: number;
        certifications: number;
    };
};

export type WorkerDocument = {
    id: number;
    document_type: string;
    document_number?: string | null;
    expires_on?: string | null;
    file_path?: string | null;
    status: string;
};

export type TrainingRecord = {
    id: number;
    course_name: string;
    certificate_code?: string | null;
    completed_on?: string | null;
    expires_on?: string | null;
    status: string;
};

export type Contract = {
    id: number;
    customer_id: number;
    customer_site_id: number;
    service_id?: number | null;
    service_package_id?: number | null;
    reference: string;
    status: string;
    starts_on: string;
    ends_on?: string | null;
    monthly_fee_halalas: Money;
    monthly_fee_sar?: string;
    vat_rate: number;
    prices_include_vat: boolean;
    pricing_model: string;
    agreed_workers: number;
    visits_per_week?: number | null;
    hours_per_visit?: string | number | null;
    planned_weekly_minutes: number;
    included_materials: boolean;
    material_policy: string;
    estimated_material_cost_halalas?: Money;
    estimated_material_cost_sar?: string;
    extra_hour_rate_halalas?: Money;
    extra_hour_rate_sar?: string;
    overtime_policy: string;
    service_scope: ServiceScopeItem[];
    terms_and_conditions?: string | null;
    sla_kpi_template: SlaKpiTemplateItem[];
    payment_plan: PaymentPlanInstallment[];
    billing_cycle: string;
    notice_days: number;
    auto_renews: boolean;
    special_terms?: string | null;
    edit_url?: string;
    detail_url?: string;
    print_url?: string;
    download_url?: string;
    assignments_count?: number;
    invoices_count?: number;
    customer: { id: number; name: string };
    site: { id: number; name: string; city?: string | null; district?: string | null };
    service?: { id: number; title: string } | null;
    service_package?: { id: number; name: string } | null;
    addendums: ContractAddendum[];
};

export type PaymentPlanInstallment = {
    label: string;
    day: number;
    percent: number;
    amount_halalas?: Money;
    due_on?: string | null;
};

export type ContractAddendum = {
    id: number;
    number: number;
    title: string;
    summary: string;
    effective_on: string;
};

export type ServiceScopeItem = {
    area: string;
    tasks: string;
};

export type ContractCustomerOption = {
    id: number;
    name: string;
    status: string;
    sites: Array<{
        id: number;
        name: string;
        city: string;
        district?: string | null;
    }>;
};

export type ServicePackageOption = {
    id: number;
    service_id: number;
    service_title?: string | null;
    name: string;
    billing_cycle: string;
    visit_frequency: string;
    worker_count: number;
    price_halalas: Money;
    price_sar?: string;
    visits_per_week?: number | null;
    hours_per_visit?: string | number | null;
    expected_labor_minutes?: number;
    material_cost_halalas?: Money;
    material_cost_sar?: string;
    vat_rate: number;
    prices_include_vat: boolean;
    sla_kpi_template?: SlaKpiTemplateItem[];
};

export type SlaKpiResult = SlaKpiTemplateItem & {
    actual: number | null;
    passed: boolean;
    performance: number;
};

export type SlaReport = {
    period_key: 'weekly' | 'monthly' | string;
    starts_on: string;
    ends_on: string;
    score: number;
    status: string;
    metrics: {
        scheduled_visits: number;
        completed_visits: number;
        missed_visits: number;
        checked_in_visits: number;
        on_time_visits: number;
        issue_count: number;
        photo_count: number;
        visits_with_photos: number;
        supervisor_review_count: number;
        checklist_items: number;
        checklist_done: number;
        attendance_rate: number;
        completion_rate: number;
        on_time_rate: number;
        checklist_completion_rate: number;
        issue_free_rate: number;
        photo_evidence_rate: number;
        supervisor_review_rate: number;
    };
    kpi_results: SlaKpiResult[];
};

export type InvoiceRow = {
    id: number;
    number: string;
    customer: string;
    contract?: string | null;
    status: string;
    issue_date?: string;
    due_date: string;
    days_overdue?: number;
    aging_bucket?: string;
    gross_total_halalas?: Money;
    gross_total_sar?: string;
    paid_total_halalas?: Money;
    paid_total_sar?: string;
    credit_total_halalas?: Money;
    credit_total_sar?: string;
    balance: Money;
    balance_halalas?: Money;
    balance_sar?: string;
    print_url?: string;
};

export type Visit = {
    id: number;
    scheduled_for?: string;
    time?: string;
    starts_at?: string;
    ends_at?: string;
    status: string;
    checked_in_at?: string | null;
    checked_out_at?: string | null;
    check_in_location?: VisitLocationEvidence | null;
    check_out_location?: VisitLocationEvidence | null;
    planned_minutes?: number;
    actual_minutes?: number;
    variance_minutes?: number;
    overtime_minutes?: number;
    billable_overtime_minutes?: number;
    overtime_status?: string;
    timer_state?: 'not_started' | 'running' | 'finished' | string;
    can_start?: boolean;
    can_finish?: boolean;
    can_save_checklist?: boolean;
    checklist_summary?: {
        total: number;
        required: number;
        completed: number;
        completed_required: number;
        open_required: number;
        progress_percent: number;
    };
    workflow?: {
        phase: string;
        primary_action?: string | null;
        steps: Array<{
            key: string;
            status: string;
        }>;
    };
    elapsed_minutes?: number | null;
    materials_used?: VisitMaterialUsage[];
    planned_revenue_halalas?: Money;
    labor_cost_halalas?: Money;
    material_cost_halalas?: Money;
    billable_overtime_halalas?: Money;
    gross_profit_halalas?: Money;
    execution_notes?: string | null;
    is_late?: boolean;
    is_missed_candidate?: boolean;
    requires_acknowledgement?: boolean;
    evidence_review?: VisitEvidenceReview | null;
    issue_note?: string | null;
    photos?: string[];
    photo_urls?: string[];
    supervisor_acknowledged_at?: string | null;
    supervisor_note?: string | null;
    completion_review_status?: string | null;
    completion_reviewed_at?: string | null;
    completion_review_note?: string | null;
    completion_reviewed_by?: {
        id: number;
        name: string;
    } | null;
    acknowledged_by?: {
        id: number;
        name: string;
    } | null;
    worker?: string | WorkerSummary | null;
    site?: string | SiteSummary | null;
    contract?: {
        id: number;
        reference: string;
        status: string;
    } | null;
    customer?: string;
    service?: string | ServiceSummary | null;
    checklist_items?: ChecklistItem[];
    checklistItems?: ChecklistItem[];
};

export type VisitMaterialUsage = {
    name: string;
    quantity?: string | null;
    cost_halalas: Money;
    cost_sar?: string;
};

export type VisitLocationEvidence = {
    latitude: string;
    longitude: string;
    accuracy_meters?: number | null;
    maps_url: string;
};

export type VisitEvidenceReview = {
    status: string;
    needs_review: boolean;
    reviewed_at?: string | null;
    review_note?: string | null;
    reviewed_by?: {
        id: number;
        name: string;
    } | null;
    photo_count: number;
    checklist_total: number;
    checklist_done: number;
    checklist_pending: number;
    checklist_blocked: number;
    has_check_in_location: boolean;
    has_check_out_location: boolean;
    has_issue: boolean;
    quality_status?: string | null;
    quality_score?: number | null;
    quality_notes?: string | null;
    quality_follow_up_required?: boolean;
    quality_reviewed_at?: string | null;
    quality_reviewed_by?: {
        id: number;
        name: string;
    } | null;
    customer_feedback?: {
        id: number;
        rating: number;
        comment?: string | null;
        submitted_at?: string | null;
    } | null;
};

export type WorkerSummary = {
    id: number;
    name: string;
    employee_code?: string;
    status?: string;
    job_role?: string;
};

export type SiteSummary = {
    id: number;
    name: string;
    city?: string | null;
    district?: string | null;
    address?: string | null;
    contact_name?: string | null;
    contact_phone?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
    formatted_address?: string | null;
    maps_url?: string | null;
    customer?: {
        id: number;
        name: string;
    } | null;
};

export type ServiceSummary = {
    id: number;
    title: string;
    category?: string;
};

export type Assignment = {
    id: number;
    contract_id?: number | null;
    customer_site_id: number;
    worker_id: number;
    service_id?: number | null;
    weekday: number;
    starts_at: string;
    ends_at: string;
    share_percent: number;
    status: string;
    team_role: string;
    task_instructions?: AssignmentTaskInstruction[];
    contract?: {
        id: number;
        reference: string;
        customer?: {
            id: number;
            name: string;
        } | null;
    } | null;
    worker?: WorkerSummary | null;
    site?: SiteSummary | null;
    service?: ServiceSummary | null;
};

export type AssignmentTaskInstruction = {
    label: string;
    is_required?: boolean;
};

export type OperationsCalendarDay = {
    date: string;
    weekday: number;
    label: string;
    day: string;
    visits_count: number;
    completed_count: number;
    scheduled_count: number;
    visits: Visit[];
    assignments: Assignment[];
};

export type OperationsWorkload = {
    worker: WorkerSummary;
    assignment_minutes: number;
    visit_minutes: number;
    visits_count: number;
    completed_visits: number;
    utilization_percent: number;
};

export type ChecklistItem = {
    id: number;
    label: string;
    is_required?: boolean;
    status: string;
    notes?: string | null;
    photo_path?: string | null;
    photo_url?: string | null;
    completed_at?: string | null;
};

export type WorkerPerformance = {
    id: number;
    name: string;
    status: string;
    target: Money;
    actual: Money;
    pace: number;
    completed_visits: number;
};
