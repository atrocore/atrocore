<div class="link-container list-group attribute-type-value {{#if disableMultiLang}}disable-multi-lang{{/if}}" data-name="{{name}}">
    {{#each optionGroups}}
	<div class="list-group-item" data-index="{{@index}}">
        <a href="javascript:" class="pull-right label-icon hidden" title="{{translate "Edit option labels"}}" data-index="{{@index}}" data-action="editLabel" style="color: var(--action-icon-color);">
            <i class="ph ph ph-globe"></i>
        </a>
		<div class="option-group">
			{{#each options}}
			<div class="option-item" data-name="{{name}}" data-index="{{@../index}}">
				<span class="text-muted">{{shortLang}} {{#if shortLang}}&#8250;{{/if}}</span>
				<input class="form-control" disabled="disabled" value="{{value}}" data-name="{{name}}" data-index="{{@../index}}">
			</div>
			{{/each}}
		</div>
	</div>
    {{/each}}
</div>
