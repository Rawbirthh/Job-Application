import axios from 'axios';

/**
 * Shared axios instance for non-Inertia async requests (fire-and-forget side
 * effects, external API calls, background sync). Inertia page visits stay on
 * the Inertia router — never use this instance to re-fetch Inertia page data
 * after mount.
 *
 * Axios reads the XSRF-TOKEN cookie and sends it back as the X-XSRF-TOKEN
 * header automatically for same-origin requests, and sends/receives JSON by
 * default, so callers stay one line long.
 */
export const http = axios.create({
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

export default http;
