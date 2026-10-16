import { createInertiaApp, router } from '@inertiajs/vue3';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, nextTick } from 'vue';
import '../css/app.css';
import { initializeTheme } from '@/composables/useAppearance';
import { focusFirstInvalidField } from '@/lib/formValidation';

const appName = import.meta.env.VITE_APP_NAME || 'Nexa';

router.on('error', () => {
    void nextTick(() => requestAnimationFrame(focusFirstInvalidField));
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
