import {UserData} from "./UserData";

export const Utils = {
    upperCaseFirst(value: string): string {
        if (!value.length) return ''
        return value[0].toUpperCase() + value.slice(1)
    },
    patchRequest(url: string, data: any) {
        return this.request('PATCH', url, data)
    },
    request(method: string, url: string, data: any) {
        const userData = UserData.get();

        const params = {
            'method': method,
            'headers': {
                'Content-Type': 'application/json',
            },
            body: undefined
        }

        if (userData?.user) {
            params['headers']['Authorization-Token'] = btoa(userData.user.userName + ':' + userData.token)
        }
        if (data) {
            if (typeof data === 'object') {
                data = JSON.stringify(data)
            }
            params.body = data
        }

        return fetch(this.joinURL('/api/v1', url), params)
    },
    joinURL(baseURL, path) {
        const normalizedBase = baseURL.endsWith('/') ? baseURL.slice(0, -1) : baseURL;
        const normalizedPath = path.startsWith('/') ? path.slice(1) : path;

        return `${normalizedBase}/${normalizedPath}`;
    }
};