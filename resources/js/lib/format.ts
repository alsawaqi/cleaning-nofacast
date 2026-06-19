export function money(value?: number | null): string {
    const amount = (value ?? 0) / 100;

    return new Intl.NumberFormat('en-SA', {
        style: 'currency',
        currency: 'SAR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function percent(value: number): string {
    return `${Math.round(value)}%`;
}

export function statusTone(status: string): string {
    const tones: Record<string, string> = {
        active: 'bg-success-50 text-success-600 border-success-500/20',
        inactive: 'bg-gray-50 text-gray-600 border-gray-200',
        prospect: 'bg-warning-50 text-warning-600 border-warning-500/20',
        blocked: 'bg-error-50 text-error-600 border-error-500/20',
        assigned: 'bg-success-50 text-success-600 border-success-500/20',
        available: 'bg-brand-50 text-brand-500 border-brand-500/20',
        on_leave: 'bg-warning-50 text-warning-600 border-warning-500/20',
        suspended: 'bg-error-50 text-error-600 border-error-500/20',
        scheduled: 'bg-brand-50 text-brand-500 border-brand-500/20',
        in_progress: 'bg-warning-50 text-warning-600 border-warning-500/20',
        issue_reported: 'bg-error-50 text-error-600 border-error-500/20',
        missed: 'bg-error-50 text-error-600 border-error-500/20',
        partial: 'bg-warning-50 text-warning-600 border-warning-500/20',
        issued: 'bg-brand-50 text-brand-500 border-brand-500/20',
        sent: 'bg-brand-50 text-brand-500 border-brand-500/20',
        completed: 'bg-success-50 text-success-600 border-success-500/20',
        paid: 'bg-success-50 text-success-600 border-success-500/20',
        overdue: 'bg-error-50 text-error-600 border-error-500/20',
        draft: 'bg-gray-50 text-gray-600 border-gray-200',
        void: 'bg-gray-50 text-gray-600 border-gray-200',
        paused: 'bg-warning-50 text-warning-600 border-warning-500/20',
        expired: 'bg-error-50 text-error-600 border-error-500/20',
        cancelled: 'bg-gray-50 text-gray-600 border-gray-200',
        pending_approval: 'bg-warning-50 text-warning-600 border-warning-500/20',
        approved: 'bg-success-50 text-success-600 border-success-500/20',
        held: 'bg-warning-50 text-warning-600 border-warning-500/20',
        cleared: 'bg-success-50 text-success-600 border-success-500/20',
        bounced: 'bg-error-50 text-error-600 border-error-500/20',
        returned: 'bg-warning-50 text-warning-600 border-warning-500/20',
    };

    return tones[status] ?? 'bg-gray-50 text-gray-600 border-gray-200';
}
