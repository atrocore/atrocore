<div class="unit-group input-group">
    <input type="text" class="unit-input form-control" name="{{name}}" value="{{value}}" autocomplete="off" pattern="[\-]?[0-9,.]*" {{#if params.maxLength}} maxlength="{{params.maxLength}}"{{/if}}>
    <div class="unit-select">
        <select name="{{unitFieldName}}" class="form-control">
            {{{options unitList unitValue translatedOptions=unitListTranslates}}}
        </select>
    </div>
</div>

