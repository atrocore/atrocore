export default interface Collection {
    name: string;

    url: string;

    total: number;

    offset: number;

    maxSize: number;

    sortBy: string;

    asc: boolean;

    where: Array<object>;

    whereAdditional: Array<object>;

    fetchOnlyCollection: boolean;

    lengthCorrection: number;

    fetch: Function;

    fetchTotal: Function,
}