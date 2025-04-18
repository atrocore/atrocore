import {writable} from 'svelte/store';

interface CollectionFactoryInterface {
    create(modelName: string, callback: Function): void
}

const data = writable<CollectionFactoryInterface>();

export const CollectionFactory = {

    setCollectionFactory(collectionFactory: CollectionFactoryInterface): void {
        data.set(collectionFactory);
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