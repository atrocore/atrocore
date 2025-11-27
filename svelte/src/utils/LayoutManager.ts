import {writable} from 'svelte/store';

interface LayoutManagerInterface {
    data: any

    resetToDefault(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any): any

    get(scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any, cache: any, isAdminPage: boolean): any

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

    get: function (scope: string, type: string, relatedScope: string | null, layoutProfileId: string, callback: any, cache: any, isAdminPage: boolean): any {
        let res = null
        data.subscribe((current: LayoutManagerInterface) => {
            if (current) {
                res = current.get(scope, type, relatedScope, layoutProfileId, callback, cache, isAdminPage);
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
    },

    clearListAndDetailCache: function () {
        data.subscribe((current: LayoutManagerInterface) => {
            if (current) {
                current.data = {}
                for (const i in localStorage) {
                    if (i.includes('app-layout') &&
                        (i.includes('-list') || i.includes('detail') || i.includes('summary'))) {
                        delete localStorage[i];
                    }
                }
            }
        })();
    }
};