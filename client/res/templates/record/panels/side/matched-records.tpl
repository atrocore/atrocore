{{#if hasMatches}}
  {{#if matches}}
    {{#each matches}}
      <div class="matched-record-status-group">
      <div class="matched-record-status-group-title">{{status}}</div>
      {{#each list}}
        <div class="matched-record-item">
          {{#if score}}<span class="matched-record-item-score">{{score}}%</span>{{/if}}
          <a href="{{link}}" class="matched-record-link">{{label}}</a>
          <div class="pull-right matched-record-item-actions" data-name="{{mrId}}"></div>
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