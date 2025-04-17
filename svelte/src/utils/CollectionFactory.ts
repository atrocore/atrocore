import {writable} from 'svelte/store';

interface CollectionFactoryInterface {
    create(modelName: string, callback: Function): void
}

const data = writable<CollectionFactoryInterface>();

export const CollectionFactory = {

    setCollectionFactory(modelFactory: CollectionFactoryInterface): void {
        data.set(modelFactory);
    },
    create(modelName: string, callback: Function): void {
        let res = null
        data.subscribe((current: CollectionFactoryInterface) => {
            if (current) {
                current.create(modelName, callback);
            }
        })();
    },
};