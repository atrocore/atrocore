import {writable} from 'svelte/store';

interface ConfigInterface {
    get(key: string): any
}

const data = writable<ConfigInterface>();

export const Config = {

    setConfig(config: ConfigInterface): void {
        data.set(config);
    },

    get(key: string): any {
        let res = null
        data.subscribe((current: ConfigInterface) => {
            if (current) {
                res = current.get(key);
            }
        })();
        return res
    },
};