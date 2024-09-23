{{#if fieldList.length}}
<div class="col-sm-6">
{{#each fieldList}}
<div class="cell form-group col-sm-12{{#if hidden}} hidden-cell{{/if}}" data-name="{{name}}">
    <label class="control-label{{#if hidden}} hidden{{/if}}" data-name="{{name}}"><span class="label-text">{{translate name scope=../scope category='fields'}}</span></label>
    <div class="field{{#if hidden}} hidden{{/if}}" data-name="{{name}}">
    {{{var viewKey ../this}}}
    </div>
</div>
{{/each}}
</div>
{{/if}}

{{#unless complexDateFieldsDisabled}}
<div class="col-sm-6">
    {{#if hasComplexCreated}}
    <div class="cell form-group col-sm-12" data-name="complexCreated">
        <label class="control-label" data-name="complexCreated"><span class="label-text">{{translate 'Created'}}</span></label>
        <div class="field" data-name="complexCreated">
            <span data-name="createdAt" class="field">{{{createdAtField}}}</span> <span class="text-muted">&raquo;</span> <span data-name="createdBy" class="field">{{{createdByField}}}</span>
        </div>
    </div>
    {{/if}}
    {{#if hasComplexModified}}
    <div class="cell form-group col-sm-12" data-name="complexModified">
        <label class="control-label" data-name="complexModified"><span class="label-text">{{translate 'Modified'}}</span></label>
        <div class="field" data-name="complexModified">
            <span data-name="modifiedAt" class="field">{{{modifiedAtField}}}</span> <span class="text-muted">&raquo;</span> <span data-name="modifiedBy" class="field">{{{modifiedByField}}}</span>
        </div>
    </div>
    {{/if}}
</div>
{{/unless}}

{{#if followersField}}
<div class="col-sm-6">
    <div class="cell form-group col-sm-12" data-name="followers">
        <label class="control-label" data-name="followers"><span class="label-text">{{translate 'Followers'}}</span></label>
        <div class="field" data-name="followers">
            {{{followersField}}}
        </div>
    </div>
</div>
{{/if}}
