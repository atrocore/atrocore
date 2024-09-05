{{#if collection.models.length}}

{{#if topBar}}
<div class="list-buttons-container clearfix">
    {{#if paginationTop}}
    <div>
        {{{pagination}}}
    </div>
    {{/if}}

    {{#if checkboxes}}
    <div class="check-all-container" data-name="r-checkbox">
        <span class="select-all-container"><input type="checkbox" class="select-all"></span>
    </div>

    {{#if massActionList}}
    <div class="btn-group actions">
        <button type="button" class="btn btn-default dropdown-toggle actions-button" data-toggle="dropdown" disabled>
        {{translate 'Actions'}}
        <span class="caret"></span>
        </button>
        <ul class="dropdown-menu">
            {{#each massActionList}}
            <li><a href="javascript:" data-action="{{./this}}" class='mass-action'>{{translate this category="massActions" scope=../scope}}</a></li>
            {{/each}}
        </ul>
    </div>
    {{/if}}
    {{/if}}

    {{#if displayTotalCount}}
        <div class="text-muted total-count">
        {{translate 'Total'}}: <span class="total-count-span">{{collection.total}}</span>
        </div>
    {{/if}}

    {{#each buttonList}}
        {{button name scope=../../scope label=label style=style}}
    {{/each}}

    <div class="sort-container">
        <div class="sort-label"> {{translate 'sort' category='labels' scope=scope}}:</div>
        <div class="btn-group sort-field">
            <button type="button" class="btn btn-default dropdown-toggle sort-field-button" data-toggle="dropdown">
                {{translate collection.sortBy category='fields' scope=scope}}
            </button>
            <ul class="dropdown-menu dropdown-menu-right">
                {{#each sortFields}}
                <li>
                    <a href="javascript:" data-action="sortByField" data-name="{{this}}">{{translate this category='fields' scope=../scope}}</a>
                </li>
                {{/each}}
            </ul>
        </div>
        <div class="btn-group sort-direction">
            <button type="button" class="btn btn-default sort-direction-button" data-action="sortByDirection">
                {{#if collection.asc}}
                &#8593;
                {{else}}
                &#8595;
                {{/if}}
            </button>
        </div>
    </div>

    <div class="text-muted selected-count hidden">{{translate 'Selected'}}: <span class="selected-count-span">0</span></div>
</div>
{{/if}}

<div class="list">
	<div>
		<div class="col-xs-12 plate">
			<div class="row">
				{{#each rowList}}
					<div class="col-xs-6 col-sm-4 col-md-3 item-container" data-id="{{./this}}">
						{{{var this ../this}}}
					</div>
				{{/each}}
			</div>
		</div>
	</div>

    <div class="show-more{{#unless showMoreActive}} hide{{/unless}}">
        <a type="button" href="javascript:" class="btn btn-default btn-block" data-action="showMore" {{#if showCount}}title="{{translate 'Total'}}: {{collection.total}}"{{/if}}>
            <span class="more-label">{{countLabel}}</span>
        </a>
    </div>
</div>

{{else}}
    {{translate 'noData'}}
{{/if}}

<style>
	.plate {
		padding: 0 15px;
	}
	.item-container {
		margin-bottom: 17px;
	}
	.list-buttons-container {
        margin-left: 5px;
	}
	.check-all-container {
	    width: 20px;
        white-space: nowrap;
	}
	.check-all-container .select-all-container {
        line-height: 19px;
        height: 19px;
	    float: left;
	}
	.check-all-container .checkbox-dropdown {
        margin-left: 3px;
        top: -1px;
	}
	.check-all-container .checkbox-dropdown > a {
	    padding: 0;
        line-height: 1;
        color: #000;
	}
	.list-buttons-container > .sort-container {
	    line-height: 0;
	    float: right;
	    margin-right: 0;
	}
	.sort-container .sort-label {
	    line-height: 19px;
	    display: inline-block;
	    vertical-align: middle;
        margin-right: 5px;
	}
	.sort-container .sort-field .dropdown-menu {
        max-height: 500px;
        overflow-y: auto;
	}
	.sort-container button.btn {
        color: #000;
        border: 0;
        padding: 0;
        background: #fff;
	}
	.sort-container button.btn:hover,
	.sort-container button.btn:focus,
	.sort-container button.btn:active,
	.sort-container .open > button.btn.sort-field-button,
	.sort-container .open > button.btn.sort-direction-button {
	    background: #fff;
        box-shadow: none;
	}
	.total-count {
	    margin-left: 30px;
	}
</style>
