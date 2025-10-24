{{#if isEmpty}}
    <span class="text-gray">{{{translate 'Null'}}}</span>
{{else}}
    <div>{{#if level}}({{/if}}
    {{#each viewDataList}}
        <div data-view-key="{{key}}" {{#if ../level}}style="margin-left: 15px;"{{/if}}>{{{var key ../this}}}</div>
        {{#unless isEnd}}
            <div {{#if ../level}}style="margin-left: 15px;"{{/if}}>
                {{translate ../operator category='logicalOperators' scope='Admin'}}
            </div>
        {{/unless}}
    {{/each}}
    {{#if level}}){{/if}}</div>
{{/if}}