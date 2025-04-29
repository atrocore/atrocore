import {writable, get} from 'svelte/store';

 let stores = new Map();
function createStore(): any  {
    const selectBoolFilters = writable<string[]>([]);
    const advancedFilterChecked = writable(false);
    const advancedFilterDisabled = writable(false);
    function toggleBoolFilters(filter: string) {
        selectBoolFilters.update((selected) => {
            if(selected.includes(filter)) {
                return selected.filter(v => v!== filter);
            }else{
                return [...selected, filter];
            }
        })
    }

    return {
        selectBoolFilters,
        advancedFilterChecked,
        advancedFilterDisabled,
        toggleBoolFilters
    }
}

export  function getGeneralFilterStore(uniqueKey: string | null)  {
    let store;
    uniqueKey  = uniqueKey ?? 'default';
    store = stores.get(uniqueKey);
    if(!store) {
        store = createStore();
        store.key = uniqueKey;
        stores.set(uniqueKey, store);
    }
    return store;
}