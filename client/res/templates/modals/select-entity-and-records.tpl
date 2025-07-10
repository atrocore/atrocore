<div class="entity-container clearfix">
    <div class="cell form-group col-sm-6 col-xs-12" data-name="selectedLink" {{#if hasAssociate}}style="padding-right: 8px"{{/if}}>
        <label class="control-label" data-name="entitySelect"><span class="label-text">{{translate 'selectedLink' category='labels' scope='Global'}}</span></label>
        <div class="field" data-name="selectedLink">
            {{{selectedLink}}}
        </div>
    </div>
    {{#if hasAssociate}}
    <div class="cell form-group col-sm-6 col-xs-12" data-name="association" style="padding-left: 8px">
        <label class="control-label" data-name="association"><span class="label-text">{{translate 'association' category='fields' scope='Product'}}</span></label>
        <div class="field" data-name="association">
            {{{association}}}
        </div>
    </div>
    {{/if}}
</div>
<div class="search-container">{{{search}}}</div>
<div class="list-container">{{{list}}}</div>
{{#if createButton}}
<div class="button-container">
    <button class="btn btn-default" data-action="create">{{createText}}</button>
</div>
{{/if}}