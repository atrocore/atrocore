import {writable} from 'svelte/store';

interface MetadataInterface {
    get(path: string[]): any
}

const data = writable<MetadataInterface>();

export const Metadata = {

    setMetadata(metadata: MetadataInterface): void {
        data.set(metadata);
    },

    get(path: string[]): any {
        let res = null
        data.subscribe((current: MetadataInterface) => {
            if (current) {
                res = current.get(path);
            }
        })();
        return res
    },
};