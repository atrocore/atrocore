<div class="link-container list-group"></div>

<div class="input-group add-team">
    <input class="main-element form-control" type="text" name="" value="" autocomplete="off" placeholder="{{placeholder}}">
    <span class="input-group-btn">
        <button data-action="selectLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><svg class="icon"><use href="client/img/icons/icons.svg#angle-up"></use></svg></button>
        {{#unless createDisabled}}
        <button data-action="createLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Create'}}"><svg class="icon"><use href="client/img/icons/icons.svg#plus"></use></svg></button>
        {{/unless}}
        {{#unless uploadDisabled}}
        <button data-action="uploadLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Upload'}}"><svg class="icon"><use href="client/img/icons/icons.svg#paperclip"></use></svg></button>
        {{/unless}}
    </span>
</div>
