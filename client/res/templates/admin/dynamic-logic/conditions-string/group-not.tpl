
    <div>{{translate 'not' category='logicalOperators' scope='Admin'}} {{#if level}}({{/if}}
        <div data-view-key="{{viewKey}}" {{#if level}}style="margin-left: 15px;"{{/if}}>{{{var viewKey this}}}</div>
    {{#if level}}){{/if}}</div>
