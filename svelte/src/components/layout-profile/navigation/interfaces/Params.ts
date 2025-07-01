export default interface Params {
    list: Array<string | object>,
    onSaved: Function,
    onEditItem?: Function,
    canReset?: boolean,
}