export interface Params {
    navigation: Array<string | object>,
    onSaved: Function,
    onEditItem?: Function
}

export interface KeyValue {
    [key: string]: any;
}

export interface Item {
    name: string,
    label: string,
    canEdit?: boolean,
    canRemove?: boolean,
    [key: string]: any;
}