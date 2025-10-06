{{#if matchedRecordsList}}
  {{#each matchedRecordsList}}
    <div><a href="{{link}}">{{label}}</a></div>
  {{/each}}
{{else}}
  <div>...</div>
{{/if}}