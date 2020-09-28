{{#if valueAndCurrency}}
     <span title="{{currencySymbol}}{{value}}">{{currencySymbol}}{{value}}</span>
{{else}}
    {{translate 'None'}}
{{/if}}
