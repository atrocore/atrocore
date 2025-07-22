<div class="link-container list-group attribute-type-value {{#if disableMultiLang}}disable-multi-lang{{/if}}" data-name="{{name}}">
    {{#each optionGroups}}
	<div class="list-group-item" data-index="{{@index}}">
		<a href="javascript:" class="pull-right remove-icon" data-index="{{@index}}" data-action="removeGroup">
            <i class="ph ph-x"></i>
		</a>
		<div class="option-group">
			{{#each options}}
			<div class="option-item" data-name="{{name}}" data-index="{{@../index}}">
				<span class="text-muted">{{shortLang}} {{#if shortLang}}&#8250;{{/if}}</span>
				<input class="form-control" value="{{value}}" data-name="{{name}}" data-index="{{@../index}}">
			</div>
			{{/each}}
		</div>
	</div>
    {{/each}}
</div>
<a class="add-attribute-type-value" href="javascript:" data-action="addNewValue">{{translate 'addNewValue' scope=scope category='labels'}}</a>
