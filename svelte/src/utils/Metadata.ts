import {writable} from 'svelte/store';

interface MetadataInterface {
    notify(message: string, type: string | null, timeout: number): void
}

const data = writable<MetadataInterface>();

export const Metadata = {

    setNotifier(notifier: MetadataInterface): void {
        data.set(notifier);
    },
    get (path: string[]) : any {
      // @ts-ignore
        return window['Espo'].metadata.get(path)
    },
};