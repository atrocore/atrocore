<div id="options" class="link-container list-group attribute-type-value {{#if disableMultiLang}}disable-multi-lang{{/if}}" data-name="{{name}}">
    {{#each optionGroups}}
	<div class="list-group-item" data-index="{{@index}}">
		<a href="javascript:" class="pull-right remove-icon" data-index="{{@index}}" data-action="removeGroup">
			<i class="fas fa-times"></i>
		</a>
		<div class="option-group">
			{{#each options}}
			<div class="option-item" data-name="{{name}}" data-index="{{@../index}}">
				<span class="text-muted">{{shortLang}} {{#if shortLang}}&#8250;{{/if}}</span>
				<input class="form-control" {{#if colorValue}} style="width: 70%" {{/if}} value="{{value}}" data-name="{{name}}" data-index="{{@../index}}">
				{{#if colorValue}}
				<input class="form-control color-input" value="{{colorValue}}" data-value="{{value}}" data-index="{{@../index}}">
				{{/if}}
			</div>
			{{/each}}
		</div>
	</div>
    {{/each}}
</div>
<a class="add-attribute-type-value" href="javascript:" data-action="addNewValue"><span class="fas fa-plus"></span></a>
<style>
	.has-error .attribute-type-value .option-group .form-control {
		border-color: #eaeaea;
		-webkit-box-shadow: none;
		-moz-box-shadow: none;
		box-shadow: none;
	}
	#options .form-control.color-input {
		width: 19%;
	}
</style>
