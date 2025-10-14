{{#if hasMatches}}
  {{#if matches}}
    {{#each matches}}
      <div class="matched-record-status-group">
      <div class="matched-record-status-group-title">{{status}}</div>
      {{#each list}}
        <div class="matched-record-item">
          <span class="matched-record-item-score">{{score}}%</span>
          <a href="{{link}}">{{label}}</a>
          <div class="pull-right inline-actions matched-record-item-actions" data-name="{{mrId}}"></div>
        </div>
      {{/each}}
      </div>
    {{/each}}
  {{else}}
    <span class="text-gray">{{translate 'noMatches'}}</span>    
  {{/if}}
{{else}}
  <div>...</div>
{{/if}}