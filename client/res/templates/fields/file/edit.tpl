<div class="input-group link">
    <input class="main-element form-control" type="text" name="{{nameName}}" value="{{nameValue}}" autocomplete="off" placeholder="{{translate 'Select'}}">
    <span class="input-group-btn">
        <button data-action="clearLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1"><i class="ph ph-minus"></i></button>
        <button data-action="selectLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Select'}}"><i class="ph ph-caret-up"></i></button>
        {{#unless uploadDisabled}}
        <button data-action="uploadLink" class="btn btn-sm btn-default btn-icon" type="button" tabindex="-1" title="{{translate 'Upload'}}"><i class="ph ph-paperclip"></i></button>
        {{/unless}}
    </span>
</div>
<input type="hidden" name="{{idName}}" value="{{idValue}}">


