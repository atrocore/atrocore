import RequestParams from "./interfaces/RequestParams";

import {UserData} from "./UserData";
import {Storage} from "./Storage";
import {Metadata} from "./Metadata";

export const Utils = {
    upperCaseFirst(value: string): string {
        if (!value.length) return ''
        return value[0].toUpperCase() + value.slice(1)
    },

    patchRequest(url: string, data: any) {
        return this.request('PATCH', url, data)
    },

    postRequest(url: string, data: any) {
        return this.request('POST', url, data)
    },

    getRequest(url: string, data: Record<string, any>) {
        const query = new URLSearchParams(data).toString()
        const finalUrl = query ? `${url}?${query}` : url

        return this.request('GET', finalUrl, null)
    },

    request(method: string, url: string, data: any) {
        const userData = UserData.get();

        const params: RequestParams = {
            'method': method,
            'headers': {
                'Content-Type': 'application/json',
            },
            body: undefined
        };

        if (userData?.user) {
            params['headers']['Authorization-Token'] = btoa(userData.user.userName + ':' + userData.token)
        }
        if (Storage.get('user', 'locale')) {
            params['headers']['Locale-Id'] = Storage.get('user', 'locale')
        }

        if (data) {
            if (typeof data === 'object') {
                data = JSON.stringify(data)
            }
            params.body = data
        }

        return fetch(this.joinURL('/api/v1', url), params)
    },

    joinURL(baseURL: string, path: string) {
        const normalizedBase = baseURL.endsWith('/') ? baseURL.slice(0, -1) : baseURL;
        const normalizedPath = path.startsWith('/') ? path.slice(1) : path;

        return `${normalizedBase}/${normalizedPath}`;
    },
    getBorder(col: string) {
        let amt = -10;
        let num = parseInt(col.slice(1), 16);
        let r = (num >> 16) + amt;

        if (r > 255) {
            r = 255;
        } else if (r < 0) {
            r = 0;
        }

        let b = ((num >> 8) & 0x00FF) + amt;

        if (b > 255) {
            b = 255;
        } else if (b < 0) {
            b = 0;
        }

        let g = (num & 0x0000FF) + amt;

        if (g > 255) {
            g = 255;
        } else if (g < 0) {
            g = 0;
        }

        let color = (g | (b << 8) | (r << 16)).toString(16);
        while (color.length < 6) {
            color = '0' + color;
        }

        return "1px solid #" + color;
    },

    getFontColor(backgroundColor: string) {
        let color = '#000';
        if (backgroundColor) {
            backgroundColor = backgroundColor.slice(1);
            let r = parseInt(backgroundColor.substr(0, 2), 16);
            let g = parseInt(backgroundColor.substr(2, 2), 16);
            let b = parseInt(backgroundColor.substr(4, 2), 16);
            let l = 1 - (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            if (l >= 0.5) {
                color = '#fff';
            }
        }
        return color;
    },

    getSystemIconUrl(name: string): string | null {
        const systemIcons: Record<string, any> = Metadata.get(['app', 'systemIcons']) || {};
        if (name in systemIcons && systemIcons[name].path) {
            return systemIcons[name].path;
        }
        return null;
    },

    getTabIcon(scope: string): string | null {
        const iconClass: string | null = Metadata.get(['clientDefs', scope, 'iconClass']) || null;
        if (iconClass) {
            const systemIcons: Record<string, any> = Metadata.get(['app', 'systemIcons']) || {};
            if (iconClass in systemIcons && systemIcons[iconClass].path) {
                return systemIcons[iconClass].path;
            }
        }

        const firstSymbol = scope.match(/\p{L}/u)?.[0] || null;
        let key = null;

        if (firstSymbol) {
            if (Number.isInteger(firstSymbol)) {
                key = firstSymbol + '-numbers-icon.svg';
            } else {
                key = firstSymbol.toLowerCase() + '-alphabet-icon.svg';
            }

            return 'client/img/icons/default/' + key;
        }

        return null;
    }
};