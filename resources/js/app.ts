import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import './bootstrap';
import { i18n } from './i18n';
import { useVisualPreferencesStore } from './preferences/visualPreferences';

const pinia = createPinia();

useVisualPreferencesStore(pinia).restore();
createApp(App).use(pinia).use(i18n).mount('#app');
