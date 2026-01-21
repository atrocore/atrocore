import type { ActionParams } from '../types/button-style';

export const getComputedClasses = (params: ActionParams, extraClassName: string = ''): string => {
    const classes = ['action'];
    if (params.size === 'small') classes.push('small');
    if (params.style) classes.push(...params.style.split(' '));
    if (extraClassName) classes.push(extraClassName);
    if (params.className) classes.push(params.className);
    return classes.filter(Boolean).join(' ');
};