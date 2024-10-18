export interface Button {
    name: string;
    label: string;
    style?: string;
    disabled?: boolean;
}

export interface Field {
    name: string;
    label: string;

    [key: string]: any;
}

export interface LayoutItem {
    name: string;
    label: string;
    width?: number;
    widthPx?: number;
    link?: boolean;
    notSortable?: boolean;
    align?: 'left' | 'right';
    view?: string;
    customLabel?: string;

    [key: string]: any;
}

export interface Params {
    scope: string;
    type: string;
    layoutProfileId: string;
    editable: boolean;
    layout: any;
    dataAttributeList: string[];
    dataAttributesDefs: any,
    allowSwitch: boolean
}