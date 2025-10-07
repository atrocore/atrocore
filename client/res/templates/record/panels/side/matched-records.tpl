{{#if hasMatchedRecordList}}
  {{#if matchedRecordsList}}
    {{#each matchedRecordsList}}
      <div><a href="{{link}}">{{label}}</a></div>
    {{/each}}
  {{else}}
    <span class="text-gray">{{translate 'noMatches'}}</span>    
  {{/if}}
{{else}}
  <div>...</div>
{{/if}}