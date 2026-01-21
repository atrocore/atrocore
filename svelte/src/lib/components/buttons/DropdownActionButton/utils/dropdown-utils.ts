import { getComputedClasses } from "../../ActionButton/utils/action-button";
import type { ActionParams } from "../../ActionButton/types/button-style";

export const getToggleClasses = (params: ActionParams, className: string): string => {
    return `${getComputedClasses(params, className)} dropdown-toggle`.trim();
};