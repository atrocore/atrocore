import {writable} from 'svelte/store';

const data = writable({});

export const UserData = {

    set(userData: any): void {
        data.set(userData);
    },

    get(): object | null {
        let res = null;

        data.subscribe((current) => {
            if (current) {
                res = current;
            }
        })();

        return res;
    },
};