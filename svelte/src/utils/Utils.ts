export const Utils = {
    upperCaseFirst(value: string): string {
        if (!value.length) return ''
        return value[0].toUpperCase() + value.slice(1)
    }
};