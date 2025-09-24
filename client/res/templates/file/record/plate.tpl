{{#if collection.models.length}}
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
        width: {{itemContainerWidth}}%;
	}
</style>
