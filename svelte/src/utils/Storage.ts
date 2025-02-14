import {writable} from 'svelte/store';

interface StorageInterface {
    get(type: string, name: string): any

    has(type: string, name: string): boolean

    clear(type: string, name: string): void

    set(type: string, name: string, value: any): void
}

const data = writable<StorageInterface>();

export const Storage = {

    setStorage(storage: StorageInterface): void {
        data.set(storage);
    },

    get(type: string, name: string): any {
        let res = null
        data.subscribe((current: StorageInterface) => {
            if (current) {
                res = current.get(type, name);
            }
        })();
        return res
    },

    has(type: string, name: string): any {
        let res = null
        data.subscribe((current: StorageInterface) => {
            if (current) {
                res = current.has(type, name);
            }
        })();
        return res
    },

    clear(type: string, name: string): any {
        data.subscribe((current: StorageInterface) => {
            if (current) {
                current.clear(type, name);
            }
        })();
    },

    set(type: string, name: string, value: any): any {
        data.subscribe((current: StorageInterface) => {
            if (current) {
                current.set(type, name, value);
            }
        })();
    },
};