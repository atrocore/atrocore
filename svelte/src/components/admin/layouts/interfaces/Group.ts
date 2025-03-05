import Field from "./Field";

export default interface Group {
    name: string;
    fields: Field[];

    [key: string]: any;
}