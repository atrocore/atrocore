import {writable} from 'svelte/store';

interface NotifierInterface {
    notify(message: string, type: string | null, timeout: number): void
}

const data = writable<NotifierInterface>();

export const Notifier = {

    setNotifier(notifier: NotifierInterface): void {
        data.set(notifier);
    },

    notify(message: string, type: string | null = null, timeout: number = 2000): void {
        data.subscribe((current: NotifierInterface) => {
            if (current) {
                current.notify(message, type, 2000);
            }
        })();
    },
};