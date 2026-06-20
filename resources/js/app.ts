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

const pages = import.meta.glob<PageModule>('./Pages/**/*.vue');
const publicPages = new Set(['Auth/Login', 'Auth/Register', 'Public/Lead']);

createInertiaApp({
    title: (title) => (title ? `${title} - Nofa Clean` : 'Nofa Clean'),
    resolve: async (name) => {
        const loadPage = pages[`./Pages/${name}.vue`];

        if (!loadPage) {
            throw new Error(`Page not found: ${name}`);
        }

        const page = await loadPage();

        const component = page.default;

        if (!publicPages.has(name)) {
            component.layout = component.layout || AppLayout;
        }

        return component;
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
