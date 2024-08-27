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

export interface Helper {
    modelFactory: {
        create: (scope: string, callback: (model: any) => void) => void;
    };
}