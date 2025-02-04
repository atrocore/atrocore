export default interface Item {
    name: string,
    label: string,
    canEdit?: boolean,
    canRemove?: boolean,
    [key: string]: any;
}