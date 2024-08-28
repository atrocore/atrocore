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
        data.subscribe((current: MetadataInterface) => {
            if (current) {
                current.get(path);
            }
        })();
    },
};