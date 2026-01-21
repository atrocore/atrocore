export type ActionParams = {
    name?: string;
    label?: string;
    action?: string;
    id?: string | number;
    size?: 'small' | 'regular' | 'large';
    style?: string;
    className?: string;
    hidden?: boolean;
    disabled?: boolean;
    tooltip?: string;
    html?: string;
    dropdown?: boolean;
    dropdownItems?: ActionParams[];
};