{{#if unitFieldName}}
<div class="row unit-group input-group">
    <div class="col-sm-4 col-xs-4">
        <input type="text" class="form-control" name="{{name}}From" value="{{fromValue}}" placeholder="{{translate 'From' scope=scope}}">
    </div>
    <div class="col-sm-4 col-xs-4">
        <input type="text" class="form-control" name="{{name}}To" value="{{toValue}}" placeholder="{{translate 'To' scope=scope}}">
    </div>
    <div class="unit-select">
        <select name="{{unitFieldName}}" class="form-control">
            {{{options unitList unitValue translatedOptions=unitListTranslates}}}
        </select>
    </div>
</div>
{{else}}
<div class="row">
    <div class="col-sm-6 col-xs-6">
        <input type="text" class="form-control" name="{{name}}From" value="{{fromValue}}" placeholder="{{translate 'From' scope=scope}}">
    </div>
    <div class="col-sm-6 col-xs-6">
        <input type="text" class="form-control" name="{{name}}To" value="{{toValue}}" placeholder="{{translate 'To' scope=scope}}">
    </div>
</div>
{{/if}}