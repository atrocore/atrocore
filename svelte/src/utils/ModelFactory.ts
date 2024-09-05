import {writable} from 'svelte/store';

interface ModelFactoryInterface {
    create(modelName: string, callback: Function): void
}

const data = writable<ModelFactoryInterface>();

export const ModelFactory = {

    setModelFactory(modelFactory: ModelFactoryInterface): void {
        data.set(modelFactory);
    },
    create(modelName: string, callback: Function): void {
        let res = null
        data.subscribe((current: ModelFactoryInterface) => {
            if (current) {
                current.create(modelName, callback);
            }
        })();
    },
};