export default interface Rule {
     condition: string;
     id: string;
     operator: string;
     value: any
     rules: Rule[]
}