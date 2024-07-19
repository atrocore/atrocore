import {writable} from 'svelte/store';

const data = writable({});

export const Language = {

    setTranslations(newTranslations: any): void {
        data.set(newTranslations);
    },

    has(name: string, category: string, scope: string): boolean {
        let res = false;

        data.subscribe((current: any) => {
            if (scope in current) {
                if (category in current[scope]) {
                    if (name in current[scope][category]) {
                        res = true;
                        return;
                    }
                }
            }
        })();

        return res;
    },

    get(scope: string, category: string, name: string): string | null | object {
        let translatedText: any = name;

        data.subscribe((current: any) => {
            if (scope in current) {
                if (category in current[scope]) {
                    if (name in current[scope][category]) {
                        translatedText = current[scope][category][name];
                        return;
                    }
                }
            }

            if (scope in current) {
                if (category in current[scope]) {
                    if (name in current[scope][category]) {
                        translatedText = current[scope][category][name];
                        return;
                    }
                }
            }
            if (scope == 'Global') {
                translatedText = name;
                return;
            }

            translatedText = null;
        })();

        return translatedText;
    },

    translate(name: string, category: null | string = null, scope: null | string = null): string {
        scope = scope || 'Global';
        category = category || 'labels';
        let res = Language.get(scope, category, name);
        if (res === null && scope != 'Global') {
            res = Language.get('Global', category, name);
        }

        // @ts-ignore: Suppress error for non string return. Normally it should be always string.
        return res;
    },

    translateOption(value: string, field: string, scope: null | string = null): string {
        let translation = Language.translate(field, 'options', scope);

        if (translation === null){
            return '';
        }else if (typeof translation === 'object') {
            // @ts-ignore: Suppress error for dynamic indexing
            return translation[value];
        }

        return value;
    },
};