<div class="link-container list-group"></div>

<div class="input-group add-team">
    <input class="main-element form-control" type="text" name="" value="" autocomplete="off" placeholder="{{placeholder}}">
    <span class="input-group-btn">
        <button data-action="selectLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><span class="fas fa-angle-up"></span></button>
        {{#unless createDisabled}}
        <button data-action="createLink" class="btn btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Create'}}"><i class="fas fa-plus"></i></button>
        {{/unless}}
    </span>
</div>
