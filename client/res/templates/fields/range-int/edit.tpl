<div class="range-container">
    <input type="text" class="form-control" name="{{name}}From" value="{{fromValue}}" placeholder="{{translate 'From' scope=scope}}">
    <input type="text" class="form-control" name="{{name}}To" value="{{toValue}}" placeholder="{{translate 'To' scope=scope}}">

    {{#if unitFieldName}}
        <div class="unit-select">
            <select name="{{unitFieldName}}" class="form-control">
                {{{options unitList unitValue translatedOptions=unitListTranslates}}}
            </select>
        </div>
    {{/if}}
</div>