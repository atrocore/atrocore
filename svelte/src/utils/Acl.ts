import {writable} from 'svelte/store';

interface AclInterface {
    getUser(): any,
    checkScope(data: any, action: any, precise: any, entityAccessData: any): boolean,
    checkModel(model: any, data: any, action: any, precise: any): boolean,
    checkModelDelete(model: any, data: any, precise: any): boolean,
    checkIsOwner(model:any): boolean,
    checkInTeam(model: any): boolean,
    check(subject: any, action: any, precise: any): boolean
    getScopeForbiddenFieldList(scope: string, action: string): any
}

const data = writable<AclInterface>();

export const Acl = {

    setAcl(acl: AclInterface): void {
        data.set(acl);
    },

    getUser() {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.getUser();
            }
        })();
        return res;
    },

    checkScope(dataV: any, action: any, precise: any, entityAccessData: any) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.checkScope(dataV, action, precise, entityAccessData);
            }
        })();
        return res;
    },

    checkModel(model: any, dataV: any, action: any, precise: any) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.checkModel(model, dataV, action, precise);
            }
        })();
        return res;
    },

    checkModelDelete(model: any, dataV: any, precise: any) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.checkModelDelete(model, dataV, precise);
            }
        })();
        return res;
    },

    checkIsOwner(model: any) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.checkIsOwner(model);
            }
        })();
        return res;
    },

    checkInTeam(model: any) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.checkInTeam(model);
            }
        })();
        return res;
    },

    check(subject: any, action: any, precise: any = null) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if(current) {
                res =  current.check(subject, action, precise);
            }
        })();
        return res;
    },

    getScopeForbiddenFieldList(scope: string, action: string) {
        let res = null
        data.subscribe((current: AclInterface) => {
            if (current) {
                res = current.getScopeForbiddenFieldList(scope, action);
            }
        })();

        return res;
    }


};