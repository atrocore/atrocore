export default interface Button {
    name: string;
    label: string;
    style?: string;
    disabled?: boolean;
    action?: Function
}