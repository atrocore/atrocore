import {writable} from 'svelte/store';

interface LayoutManagerInterface {
    resetToDefault(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any): any

    get(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any, cache: any): any

    set(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, layout: any, callback: any): any
}

const data = writable<LayoutManagerInterface>();

export const LayoutManager = {
    setLayoutManager(layoutManager: LayoutManagerInterface): void {
        data.set(layoutManager);
    },

    resetToDefault(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any) {
        let res = null
        data.subscribe((current: LayoutManagerInterface) => {
            if (current) {
                res = current.resetToDefault(scope, type, relatedScope, layoutProfileId, callback);
            }
        })();
        return res
    },

    get: function (scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any, cache: any): any {
        let res = null
        data.subscribe((current: LayoutManagerInterface) => {
            if (current) {
                res = current.get(scope, type, relatedScope, layoutProfileId, callback, cache);
            }
        })();
        return res
    },

    set: function (scope: string, type: string, relatedScope: string | null, layoutProfileId: string, layout: any, callback: any): any {
        let res = null
        data.subscribe((current: LayoutManagerInterface) => {
            if (current) {
                res = current.set(scope, type, relatedScope, layoutProfileId, layout, callback);
            }
        })();
        return res
    }
};