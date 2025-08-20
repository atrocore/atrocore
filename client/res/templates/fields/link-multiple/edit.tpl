<div class="input-group link add-team">
    <input class="main-element form-control" type="text" name="" value="" autocomplete="off" placeholder="{{placeholder}}">
    <span class="input-group-btn">
        <button data-action="selectLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><i class="ph ph-caret-up"></i></button>
        {{#unless createDisabled}}
        <button data-action="createLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Create'}}"><i class="ph ph-plus"></i></button>
        {{/unless}}
        {{#unless uploadDisabled}}
        <button data-action="uploadLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Upload'}}"><i class="ph ph-paperclip"></i></button>
        {{/unless}}
    </span>
</div>
