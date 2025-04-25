<select class="form-control search-type input-sm" {{#if hideSearchType}} hidden {{/if}} name="{{name}}-type">
    {{options searchTypeList searchType field='searchRanges'}}
</select>
<div class="primary">
    <select class="form-control input-sm" name="{{typeName}}">
        {{options foreignScopeList searchParams.valueType category='scopeNames'}}
    </select>
    <div class="input-group">
        <input class="form-control input-sm" type="text" name="{{nameName}}" value="{{searchParams.valueName}}" autocomplete="off" placeholder="{{translate 'Select'}}">
        <span class="input-group-btn">
            <button type="button" class="btn btn-sm btn-default btn-icon" data-action="selectLink" tabindex="-1" title="{{translate 'Select'}}"><i class="ph ph-caret-up"></i></button>
            <button type="button" class="btn btn-sm btn-default btn-icon" data-action="clearLink" tabindex="-1"><i class="ph ph-x"></i></button>
        </span>
    </div>
    <input type="hidden" name="{{idName}}" value="{{searchParams.valueId}}">
</div>


