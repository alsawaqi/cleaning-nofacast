import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import AppLayout from './Layouts/AppLayout.vue';
import { applyDocumentLocaleFromPageProps } from './lib/i18n';

type PageModule = {
    default: DefineComponent & {
        layout?: unknown;
    };
};

const pages = import.meta.glob<PageModule>('./Pages/**/*.vue', { eager: true });
const publicPages = new Set(['Auth/Login', 'Public/Lead']);

createInertiaApp({
    title: (title) => (title ? `${title} - Nofacast Clean` : 'Nofacast Clean'),
    resolve: (name) => {
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        if (!publicPages.has(name)) {
            const component = page.default as Record<string, unknown>;
            component.layout = component.layout || AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        applyDocumentLocaleFromPageProps(
            (props as { initialPage?: { props?: Record<string, unknown> } }).initialPage?.props,
        );

        router.on('success', (event) => {
            applyDocumentLocaleFromPageProps(
                (event.detail.page.props ?? {}) as Record<string, unknown>,
            );
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0f766e',
    },
});
