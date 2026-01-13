{{#if groups.length}}
<div class="group-container">
    {{#each groups}}
    <div class="group" data-name="{{key}}">
        <div class="group-name">
            {{#if id}}
            <a href="#{{../groupScope}}/view/{{id}}"><strong>{{label}}</strong></a>
            {{else}}
            <strong>{{label}}</strong>
            {{/if}}
        </div>
        <div class="list-container">&nbsp;{{translate 'Loading...'}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<div class="list-container"><div class="no-data-container">{{translate 'No Data'}}</div></div>
{{/if}}
