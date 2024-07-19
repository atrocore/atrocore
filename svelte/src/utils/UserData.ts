import {writable} from 'svelte/store';

const data = writable({});

interface UserDataInterface {
    user: {
        userName: string
    },
    token: string
}

export const UserData = {

    set(userData: any): void {
        data.set(userData);
    },

    get(): UserDataInterface | null {
        let res = null;

        data.subscribe((current) => {
            if (current) {
                res = current;
            }
        })();

        return res;
    },
};