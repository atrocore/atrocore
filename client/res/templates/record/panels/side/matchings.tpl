<div style="overflow: hidden;margin-bottom: 10px;padding-left: 1px; padding-right: 1px;">
    <div style="display: inline-block;">
        <div class="mr-content-filter"></div>
    </div>
    <div style="display: inline-block;float: right;">
        <button data-action="refreshMatchedRecords" class="refresh" title="{{translate 'Refresh'}}"><i class="ph ph-arrows-clockwise"></i></button>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-12 form-group">
        {{#if matchesList}}
        {{#each matchesList}}
        <div class="form-group" style="margin-bottom: 20px">
            <label class="control-label"><span class="label-text" data-name="{{name}}">{{label}}</span></label>
            <div class="list-container" data-name="{{name}}"></div>
        </div>
        {{/each}}
        {{/if}}
    </div>
</div>