import { createApp } from 'vue';
import { createPinia } from 'pinia';
import piniaPersistedstate from 'pinia-plugin-persistedstate';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

import App from './App.vue';
import router from './router';
import i18n from './plugins/i18n';
import './assets/css/app.css';

const app = createApp(App);
const pinia = createPinia();
pinia.use(piniaPersistedstate);

app.use(pinia);
app.use(router);
app.use(i18n);
app.use(Toast, { position: 'top-right', timeout: 4000, hideProgressBar: true, closeOnClick: true });

app.mount('#app');
