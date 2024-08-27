import {writable} from 'svelte/store';

interface LayoutManagerInterface {
    notify(message: string, type: string | null, timeout: number): void
}

const data = writable<LayoutManagerInterface>();

export const LayoutManager = {


    resetToDefault(scope: string, type: string, callback: any) {
        return window['Espo']['layout-manager'].resetToDefault(scope, type, callback);
    },

    get: function (scope, type, callback, cache): any {
        return window['Espo']['layout-manager'].get(scope, type, callback, cache)
    },

    set: function (scope, type, layout, callback): any {
        return window['Espo']['layout-manager'].set(scope, type, layout, callback)
    }
};