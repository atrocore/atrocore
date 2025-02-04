export default interface LayoutItem {
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
