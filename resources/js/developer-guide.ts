import { createApp } from 'vue';
import { createPinia } from 'pinia';
import DeveloperGuideApp from './developer-guide/DeveloperGuideApp.vue';
import { i18n } from './i18n';
import { useVisualPreferencesStore } from './preferences/visualPreferences';

const pinia = createPinia();

useVisualPreferencesStore(pinia).restore();
createApp(DeveloperGuideApp).use(pinia).use(i18n).mount('#developer-guide');
