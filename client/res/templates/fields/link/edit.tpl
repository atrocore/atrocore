<div class="input-group">
    <input class="main-element form-control" type="text" name="{{nameName}}" value="{{nameValue}}" autocomplete="off" placeholder="{{translate 'Select'}}">
    <span class="input-group-btn">
        <button data-action="selectLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><svg class="icon"><use href="client/img/icons/icons.svg#angle-up"></use></svg></button>
        {{#unless createDisabled}}
        <button data-action="createLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Create'}}"><svg class="icon"><use href="client/img/icons/icons.svg#plus"></use></svg></button>
        {{/unless}}
        <button data-action="clearLink" class="btn btn-default btn-icon" type="button" tabindex="-1"><svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg></button>
    </span>
</div>
<input type="hidden" name="{{idName}}" value="{{idValue}}">


