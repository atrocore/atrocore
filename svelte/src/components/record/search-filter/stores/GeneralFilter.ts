import {writable, get} from 'svelte/store';

function createStore(): any  {
    const  selectBoolFilters = writable<string[]>([]);
    const advancedFilterChecked = writable(false);
    function toggleBoolFilters(filter: string) {
        selectBoolFilters.update((selected) => {
            if(selected.includes(filter)) {
                return selected.filter(v => v!== filter);
            }else{
                return [filter, ...selected];
            }
        })
    }

    return {
        selectBoolFilters,
        advancedFilterChecked,
        toggleBoolFilters
    }
}

export const generalFilterStore = createStore();