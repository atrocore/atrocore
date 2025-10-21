{{#if conditionTypes.length }}
    {{#each conditionTypes}}
        <details class="conditions-container" data-name="{{this}}">
            <summary><span>{{ translate this category='fields' scope='EntityField' }}</span></summary>
            <div class="conditions"></div>
        </details>
    {{/each}}
{{else}}
    <span class="text-gray">{{{translate 'Null'}}}</span>
{{/if}}
