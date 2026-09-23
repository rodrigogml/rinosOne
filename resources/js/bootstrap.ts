import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

axios.interceptors.response.use((response) => {
    const refreshedCsrfToken = response.headers['x-csrf-token'];

    if (typeof refreshedCsrfToken === 'string') {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = refreshedCsrfToken;
    }

    return response;
});
