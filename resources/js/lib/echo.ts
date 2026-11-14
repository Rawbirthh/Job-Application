import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Lazily created Echo client for the self-hosted Reverb instance.
 *
 * Returns null when VITE_REVERB_APP_KEY is not configured (CI, phpunit,
 * local setups without Reverb) so pages can skip realtime subscriptions
 * and degrade to normal server rendering without errors.
 */
let client: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
    if (client !== null) {
        return client;
    }

    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    try {
        client = new Echo({
            broadcaster: 'reverb',
            key,
            Pusher,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1',
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? '8080'),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? '8080'),
            forceTLS:
                (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        return client;
    } catch (error) {
        console.warn('Realtime messaging is unavailable:', error);
        client = null;

        return null;
    }
}
