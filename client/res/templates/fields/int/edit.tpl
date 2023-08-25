{{#if unitFieldName}}
<div class="unit-group input-group">
    <input type="text" class="unit-input form-control" name="{{name}}" value="{{value}}" autocomplete="off" pattern="[\-]?[0-9]*">
    <div class="unit-select">
        <select name="{{unitFieldName}}" class="form-control">
            {{{options unitList unitValue translatedOptions=unitListTranslates}}}
        </select>
    </div>
</div>
{{else}}
<input type="text" class="main-element form-control" name="{{name}}" value="{{value}}" autocomplete="off" pattern="[\-]?[0-9]*">
{{/if}}