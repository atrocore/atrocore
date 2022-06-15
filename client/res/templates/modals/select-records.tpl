<div class="search-container">{{{search}}}</div>
<div class="list-container for-table-view">{{{list}}}</div>
{{#if hasTree}}
<div class="list-container for-tree-view">
   <div class="list-buttons-container clearfix">
      {{#if hasTotalCount}}
      <div class="text-muted total-count">{{translate 'Shown'}}: <span class="shown-count-span">{{totalCount}}</span> | {{translate 'Total'}}: <span class="total-count-span">{{totalCount}}</span></div>
      {{/if}}
      <div class="records-tree"></div>
   </div>
</div>
{{/if}}
{{#if createButton}}<div class="button-container"><button class="btn btn-default" data-action="create">{{createText}}</button></div>{{/if}}