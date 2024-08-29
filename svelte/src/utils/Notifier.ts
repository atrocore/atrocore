import {writable} from 'svelte/store';
import {Language} from './Language'

interface NotifierInterface {
    notify(message: string | boolean, type: string | null, timeout: number): void

    confirm(message: string, o: any, callback: any, context: any): void
}

const data = writable<NotifierInterface>();

export const Notifier = {

    setNotifier(notifier: NotifierInterface): void {
        data.set(notifier);
    },

    notify(message: string | boolean, type: string | null = null, timeout: number = 2000): void {
        data.subscribe((current: NotifierInterface) => {
            if (current) {
                current.notify(message, type, 2000);
            }
        })();
    },

    confirm(o: any = null, callback: any = null, context: any): void {
        data.subscribe((current: NotifierInterface) => {
            if (current) {
                let confirmStyle = null;
                let message = null;
                let confirmText = null;
                if (typeof o === 'string' || o instanceof String) {
                    message = o;
                    confirmText = Language.translate('Yes');
                } else {
                    o = o || {};
                    message = o.message;
                    confirmText = o.confirmText;
                    confirmStyle = o.confirmStyle || null;
                }
                current.confirm(message, {
                    confirmText: confirmText,
                    cancelText: Language.translate('Cancel'),
                    confirmStyle: confirmStyle
                }, callback, context);
            }
        })();
    },
};