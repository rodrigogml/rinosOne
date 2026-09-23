import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.axios.interceptors.response.use((response) => {
    const refreshedCsrfToken = response.headers['x-csrf-token'];

    if (typeof refreshedCsrfToken === 'string') {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = refreshedCsrfToken;
    }

    return response;
});
