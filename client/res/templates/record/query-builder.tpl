{{#if hasAttributeButton}}
<button class="btn-link btn" data-action="add-attribute-filter"><div>{{translate 'Add Attribute'}}</div></button>
{{/if}}
<div class="query-builder"></div>
<div class="row filter-actions">
    <button class="btn-link btn" data-action="search"><div>{{translate 'Apply filter'}}</div></button>
    <span class="pipeline">|</span>
    <button class="btn-link btn" data-action="reset-filter"><div>{{translate 'Reset filter'}}</div></button>
</div>