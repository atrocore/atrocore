<div class="link-container list-group attribute-type-value data-name="{{name}}">
    {{#each optionGroups}}
	<div class="list-group-item">
        <div class="option-group">
			<div class="option-item">
                <div class="disable-options" data-name="{{disableOptions}}">{{{disableOptions}}}</div>
                <div class="condition-group" data-name="{{conditionGroup}}">{{{conditionGroup}}}</div>
			</div>
		</div>
	</div>
    {{/each}}
</div>
