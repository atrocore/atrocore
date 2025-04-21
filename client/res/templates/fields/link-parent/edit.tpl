<div class="input-group">
    <span class="input-group-btn">
        <select class="form-control" name="{{typeName}}">
            {{options foreignScopeList foreignScope category='scopeNames'}}
        </select>
    </span>
    <input class="main-element form-control middle-element" type="text" name="{{nameName}}" value="{{nameValue}}" autocomplete="off" placeholder="{{translate 'Select'}}">
    <span class="input-group-btn">
        <button data-action="selectLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><i class="fas fa-angle-up"></i></button>
        {{#unless createDisabled}}
        <button data-action="createLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Create'}}"><i class="fas fa-plus"></i></button>
        {{/unless}}
        <button data-action="clearLink" class="btn btn-default btn-icon" type="button" tabindex="-1"><i class="ph ph-x"></i></button>
    </span>
</div>
<input type="hidden" name="{{idName}}" value="{{idValue}}">
