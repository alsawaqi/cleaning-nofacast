import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { AppSharedProps } from '../types';

type SharedProps = AppSharedProps & Record<string, unknown>;

function readMessage(messages: Record<string, unknown>, key: string): unknown {
    return key.split('.').reduce<unknown>((value, segment) => {
        if (value && typeof value === 'object' && segment in value) {
            return (value as Record<string, unknown>)[segment];
        }

        return undefined;
    }, messages);
}

export function translateFromMessages(
    messages: Record<string, unknown> | undefined,
    key: string,
    fallback?: string,
    replacements: Record<string, string | number> = {},
): string {
    const message = messages ? readMessage(messages, key) : undefined;
    const template = typeof message === 'string' ? message : fallback ?? key;

    return Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        template,
    );
}

export function useI18n() {
    const page = usePage<SharedProps>();
    const locale = computed(() => page.props.i18n?.locale ?? page.props.app?.locale ?? 'en');
    const dir = computed(() => page.props.i18n?.dir ?? page.props.app?.dir ?? 'ltr');
    const messages = computed(() => page.props.i18n?.messages ?? {});

    function t(key: string, fallback?: string, replacements: Record<string, string | number> = {}): string {
        return translateFromMessages(messages.value, key, fallback, replacements);
    }

    return {
        dir,
        locale,
        t,
    };
}

export function applyDocumentLocaleFromPageProps(props: Record<string, unknown> | undefined): void {
    if (typeof document === 'undefined' || !props) {
        return;
    }

    const shared = props as SharedProps;
    const locale = shared.i18n?.locale ?? shared.app?.locale ?? 'en';
    const dir = shared.i18n?.dir ?? shared.app?.dir ?? (locale === 'ar' ? 'rtl' : 'ltr');

    document.documentElement.lang = locale;
    document.documentElement.dir = dir;
}
