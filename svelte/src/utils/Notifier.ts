import {writable} from 'svelte/store';

interface NotifierInterface {
    notify(message: string|boolean, type: string | null, timeout: number): void

    confirm(message: string, o: any, callback: any): void
}

const data = writable<NotifierInterface>();

export const Notifier = {

    setNotifier(notifier: NotifierInterface): void {
        data.set(notifier);
    },

    notify(message: string|boolean, type: string | null = null, timeout: number = 2000): void {
        data.subscribe((current: NotifierInterface) => {
            if (current) {
                current.notify(message, type, 2000);
            }
        })();
    },

    confirm(message: string, o: any = null, callback: any = null, context: any): void {
        data.subscribe((current: NotifierInterface) => {
            if (current) {
                current.confirm(message, o, callback);
            }
        })();
    },
};