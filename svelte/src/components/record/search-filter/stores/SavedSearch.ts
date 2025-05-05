import {writable, get} from 'svelte/store';
import SavedSearch from '../interfaces/SavedSearch'
import Collection from '../../../../utils/interfaces/Collection'
import {UserData} from "../../../../utils/UserData";


let stores = new Map();

function createStore(): any {
    const savedSearchItems = writable<SavedSearch[]>([]);
    const selectedSavedItemIds = writable<string[]>([]);
    const collection = writable<Collection | null>(null);
    const loading = writable(false);
    let savedSearchFetched: boolean = false;

    async function fetchSavedSearch(scope: string) {
        const userData = UserData.get();
        if (!userData) {
            return;
        }

        if (get(loading) || savedSearchFetched) {
            return;
        }

        loading.set(true);

        let where = [{
            type: 'equals',
            attribute: 'entityType',
            value: scope
        }];
        const response = await fetch('/api/v1/SavedSearch?'+ window.$.param({collectionOnly:true, maxSize:20, where}), {
            'method': 'GET',
            'headers': {
                'Content-Type': 'application/json',
                'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
            }
        })

        savedSearchFetched = true;

        if (response.ok) {
            let data = await response.json();
            savedSearchItems.set(data.list);
            loading.set(false);
            return data.list;
        }
    }

    async function saveSavedSearch(item: SavedSearch, id: string | null = null): Promise<void> {
        const userData = UserData.get();
        if (!userData) {
            return;
        }

        const response = await fetch(id ? `/api/v1/SavedSearch/${id}` : '/api/v1/SavedSearch', {
            'method': id ? 'PUT' : 'POST',
            'headers': {
                'Content-Type': 'application/json',
                'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
            },
            'body': JSON.stringify(item),
        })

        if (response.ok) {
            const data = await response.json();
            savedSearchItems.update((list) => {
                if(id !== null) {
                    return list.map(item => item.id === id ? data : item);
                }
                return [data, ...list];
            })
            return data;
        }
    }

    async function removeSavedSearch(itemId: string) {
        const userData = UserData.get();
        if (!userData) {
            return;
        }
        const response = await fetch(`/api/v1/SavedSearch/${itemId}`, {
            'method': 'DELETE',
            'headers': {
                'Content-Type': 'application/json',
                'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
            },
        })

        if (response.ok) {
            savedSearchItems.update((list) => {
                return list.filter(v => v.id !== itemId)
            })
        }
    }

    function toggleSavedItemSelection(itemId: string): void {
        selectedSavedItemIds.update((selected) => {
            if (selected.includes(itemId)) {
                return selected.filter((id) => id !== itemId);
            } else {
                return [...selected, itemId];
            }
        });
    }

    return {
        savedSearchItems,
        selectedSavedItemIds,
        collection,
        loading,
        fetchSavedSearch,
        toggleSavedItemSelection,
        saveSavedSearch,
        removeSavedSearch
    }
}

export function getSavedSearchStore(scope: string, uniqueKey: string | null, initial: Record<string, any> | null = null)  {
    let store;
    uniqueKey = scope + '_' + (uniqueKey ?? 'default');
    store = stores.get(uniqueKey);
    if(!store) {
        store = createStore();
        store.key = uniqueKey;
        stores.set(uniqueKey, store);

        if (initial) {
            store.savedSearchItems.set(initial.items ?? []);
            store.selectedSavedItemIds.set(initial.selected ?? []);
        }

        store.fetchSavedSearch(scope);
    }
    return store;
}

