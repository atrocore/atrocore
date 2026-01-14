{{#if loadingGroups}}
Loading...
{{else}}
{{#if groups.length}}
<div class="group-container">
    {{#each groups}}
    <div class="group" data-name="{{key}}">
        <div class="list-container"><div style="padding: 8px 14px">{{translate 'Loading...'}}</div></div>
    </div>
    {{/each}}
</div>
{{else}}
<div class="list-container"><div class="no-data-container">{{translate 'No Data'}}</div></div>
{{/if}}
{{/if}}