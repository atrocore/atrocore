<div class="entity-container clearfix">
    <div class="cell form-group col-sm-6 col-xs-12" data-name="selectedLink">
        <label class="control-label" data-name="entitySelect"><span class="label-text">{{translate 'selectedLink' category='labels' scope='Global'}}</span></label>
        <div class="field" data-name="selectedLink">
            {{{selectedLink}}}
        </div>
    </div>
</div>
<div class="search-container">{{{search}}}</div>
<div class="list-container">{{{list}}}</div>
{{#if createButton}}
<div class="button-container">
    <button class="btn btn-default" data-action="create">{{createText}}</button>
</div>
{{/if}}
