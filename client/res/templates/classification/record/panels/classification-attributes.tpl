{{#if groups.length}}
<div class="group-container">
    {{#each groups}}
    <div class="group" data-name="{{key}}">
        <div class="group-name">
            {{#if id}}
            <a href="#{{../groupScope}}/view/{{id}}"><strong>{{label}}</strong></a>
            {{#if editable}}
            <div class="pull-right btn-group">
	            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
                    <i class="ph ph-caret-down"></i>
	            </button>
	            <ul class="dropdown-menu">
	                <li><a href="javascript:" class="action" data-action="unlinkAttributeGroup" data-id="{{id}}">{{translate 'unlinkRelatedAttribute' category='labels' scope='ClassificationAttribute'}}</a></li>
	            </ul>
			</div>
			{{/if}}
            {{else}}
            <strong>{{label}}</strong>
            {{/if}}
        </div>
        <div class="list-container">&nbsp;{{translate 'Loading...'}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<div class="list-container"><div class="no-data-container">{{translate 'No Data'}}</div></div>
{{/if}}
