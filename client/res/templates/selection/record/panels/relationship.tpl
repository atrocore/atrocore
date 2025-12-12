
{{#if rowList.length }}
<div class="records">
	{{#each rowList}}
		<div class="row {{#unless @first }} not-first {{/unless}}" data-id="{{./this}}">
			{{{var this ../this}}}
		</div>
	{{/each}}
</div>
{{else}}
<div class="list-container">{{translate 'No Data'}}</div>
{{/if}}