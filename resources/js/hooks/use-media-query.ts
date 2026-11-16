import { useSyncExternalStore } from 'react';

/**
 * Subscribe to a CSS media query and re-render on change.
 *
 * Returns `false` during server rendering / before hydration.
 */
export function useMediaQuery(query: string): boolean {
    const subscribe = (onChange: () => void) => {
        const mediaQueryList = window.matchMedia(query);
        mediaQueryList.addEventListener('change', onChange);

        return () => mediaQueryList.removeEventListener('change', onChange);
    };

    return useSyncExternalStore(
        subscribe,
        () => window.matchMedia(query).matches,
        () => false,
    );
}
